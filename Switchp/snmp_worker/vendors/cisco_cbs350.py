"""
Cisco CBS350 OID mapper.
Optimized for Cisco CBS350 Access Switch (Small Business).
"""

from typing import Dict, List, Any
from .base import VendorOIDMapper, DeviceInfo, PortInfo


class CiscoCBS350Mapper(VendorOIDMapper):
    """OID mapper for Cisco CBS350 switches."""
    
    def __init__(self):
        """Initialize Cisco CBS350 mapper."""
        super().__init__()
        self.vendor_name = "cisco"
        self.model_name = "cbs350"
    
    def get_device_info_oids(self) -> List[str]:
        """Get OIDs for device information."""
        return [
            self.OID_SYS_DESCR,
            self.OID_SYS_NAME,
            self.OID_SYS_UPTIME,
            self.OID_IF_NUMBER,
            self.OID_SYS_CONTACT,
            self.OID_SYS_LOCATION
        ]
    
    def parse_device_info(self, snmp_data: Dict[str, Any]) -> DeviceInfo:
        """Parse device information from SNMP data."""
        sys_descr = str(snmp_data.get(self.OID_SYS_DESCR, "Unknown"))
        sys_name = str(snmp_data.get(self.OID_SYS_NAME, "Unknown"))
        sys_uptime = int(snmp_data.get(self.OID_SYS_UPTIME, 0))
        
        # For CBS350-24FP-4G: 24 PoE ports + 4 SFP ports = 28 physical ports
        # Don't use if_number as it includes virtual interfaces
        # Instead, count actual physical ethernet ports from interface descriptions
        physical_port_count = 0
        for oid, value in snmp_data.items():
            if self.OID_IF_DESCR in oid and not oid.endswith(self.OID_IF_DESCR):
                descr = str(value).lower()
                # Count only physical ethernet ports (GigabitEthernet)
                # CBS350-24FP has ports 1-28: gi1-gi24 (PoE) + gi25-gi28 (SFP)
                if any(x in descr for x in ['gi', 'gigabit', 'ethernet']):
                    if not any(x in descr for x in ['vlan', 'management', 'null', 'loopback', 'port-channel']):
                        physical_port_count += 1
        
        # If we couldn't count ports, fall back to a safe default for CBS350-24FP
        # Model typically indicates the port count (24 in CBS350-24FP)
        if physical_port_count == 0:
            if '24' in sys_descr:
                physical_port_count = 28  # 24 PoE + 4 SFP
            else:
                physical_port_count = int(snmp_data.get(self.OID_IF_NUMBER, 0))
        
        return DeviceInfo(
            system_description=sys_descr,
            system_name=sys_name,
            system_uptime=sys_uptime,
            total_ports=physical_port_count
        )
    
    def get_port_info_oids(self) -> List[str]:
        """Get OIDs for port information."""
        return [
            self.OID_IF_DESCR,
            self.OID_IF_NAME,
            self.OID_IF_ALIAS,
            self.OID_IF_TYPE,
            self.OID_IF_MTU,
            self.OID_IF_SPEED,
            self.OID_IF_HIGH_SPEED,
            self.OID_IF_ADMIN_STATUS,
            self.OID_IF_OPER_STATUS,
            self.OID_IF_PHYS_ADDRESS,
            '1.3.6.1.2.1.17.7.1.4.2.1.4'  # dot1qVlanStaticEgressPorts - VLAN membership bitmap
        ]
    
    def parse_port_info(self, snmp_data: Dict[str, Any]) -> List[PortInfo]:
        """
        Parse port information from SNMP data.
        
        For CBS350, we filter only physical ethernet ports.
        CBS350 uses simpler port naming like "gi1", "gi2" etc.
        """
        ports = {}
        
        # VLAN EGRESS MASK PARSING
        # First, parse VLAN membership from egress bitmaps
        # This gives us accurate VLAN assignments for each port
        vlan_port_map = {}  # port_number -> vlan_id mapping
        
        for oid, value in snmp_data.items():
            # OID format: 1.3.6.1.2.1.17.7.1.4.2.1.4.0.VLAN_ID
            if '1.3.6.1.2.1.17.7.1.4.2.1.4' in oid:
                parts = oid.split('.')
                if len(parts) >= 2:
                    try:
                        vlan_id = int(parts[-1])  # Last part is VLAN ID
                        
                        # Value is an octet string (binary bitmap)
                        # Each bit represents a port (MSB first, Cisco convention)
                        mask_bytes = value if isinstance(value, bytes) else value.encode('latin-1')
                        mask_len = len(mask_bytes)
                        
                        # Parse bitmap to find which ports are in this VLAN
                        # CBS350-24FP has 28 ports (24 PoE + 4 SFP)
                        # Process up to however many ports the mask supports
                        max_ports = min(mask_len * 8, 52)  # Support up to 52 ports (6.5 bytes)
                        for port_num in range(1, max_ports + 1):
                            byte_pos = (port_num - 1) // 8
                            bit_pos = 7 - ((port_num - 1) % 8)  # MSB first
                            
                            if byte_pos < mask_len:
                                byte_val = mask_bytes[byte_pos] if isinstance(mask_bytes[byte_pos], int) else ord(mask_bytes[byte_pos])
                                bit_set = (byte_val >> bit_pos) & 1
                                
                                if bit_set:
                                    # Port is in this VLAN
                                    # Prefer non-VLAN-1 assignments (access ports)
                                    if port_num not in vlan_port_map or vlan_id > 1:
                                        vlan_port_map[port_num] = vlan_id
                    except (ValueError, IndexError, AttributeError) as e:
                        # Skip malformed data
                        pass
        
        # Parse interface descriptions to get port numbers
        for oid, value in snmp_data.items():
            if self.OID_IF_DESCR in oid and not oid.endswith(self.OID_IF_DESCR):
                if_index = int(oid.split('.')[-1])
                descr = str(value)
                
                # Filter for physical ethernet ports
                # CBS350 uses names like "gi1", "gi2" or "Gi1/0/1"
                # Include fiber/SFP ports for LLDP and link status monitoring
                # (but we'll skip change alarms for them in port_change_detector)
                if any(x in descr.lower() for x in ['gi', 'gigabit', 'ethernet', 'sfp', 'fiber']):
                    # Skip management, virtual interfaces, and port-channels
                    if not any(x in descr.lower() for x in ['vlan', 'management', 'null', 'loopback', 'port-channel']):
                        ports[if_index] = {
                            'port_number': if_index,
                            'port_name': descr,
                            'port_alias': '',
                            'admin_status': 'unknown',
                            'oper_status': 'unknown',
                            'port_type': '',
                            'port_speed': 0,
                            'port_mtu': 0,
                            'mac_address': None,
                            'vlan_id': None
                        }
        
        # Parse interface names
        for oid, value in snmp_data.items():
            if self.OID_IF_NAME in oid and not oid.endswith(self.OID_IF_NAME):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    ports[if_index]['port_name'] = str(value)
        
        # Parse interface aliases (descriptions)
        for oid, value in snmp_data.items():
            if self.OID_IF_ALIAS in oid and not oid.endswith(self.OID_IF_ALIAS):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    ports[if_index]['port_alias'] = str(value)
        
        # Parse admin status
        for oid, value in snmp_data.items():
            if self.OID_IF_ADMIN_STATUS in oid and not oid.endswith(self.OID_IF_ADMIN_STATUS):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    ports[if_index]['admin_status'] = self.status_to_string(int(value))
        
        # Parse operational status
        for oid, value in snmp_data.items():
            if self.OID_IF_OPER_STATUS in oid and not oid.endswith(self.OID_IF_OPER_STATUS):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    ports[if_index]['oper_status'] = self.status_to_string(int(value))
        
        # Parse interface type
        for oid, value in snmp_data.items():
            if self.OID_IF_TYPE in oid and not oid.endswith(self.OID_IF_TYPE):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    ports[if_index]['port_type'] = str(value)
        
        # Parse speed (prefer high-speed if available)
        for oid, value in snmp_data.items():
            if self.OID_IF_HIGH_SPEED in oid and not oid.endswith(self.OID_IF_HIGH_SPEED):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    # High speed is in Mbps, convert to bps
                    ports[if_index]['port_speed'] = int(value) * 1000000
            elif self.OID_IF_SPEED in oid and not oid.endswith(self.OID_IF_SPEED):
                if_index = int(oid.split('.')[-1])
                if if_index in ports and ports[if_index]['port_speed'] == 0:
                    ports[if_index]['port_speed'] = int(value)
        
        # Parse MTU
        for oid, value in snmp_data.items():
            if self.OID_IF_MTU in oid and not oid.endswith(self.OID_IF_MTU):
                if_index = int(oid.split('.')[-1])
                if if_index in ports:
                    ports[if_index]['port_mtu'] = int(value)
        
        # Assign VLANs from egress map
        # Try to map interface index to logical port number
        # For CBS350, interface indexes typically match port numbers for physical ports
        for if_index, port_data in ports.items():
            # Try to extract port number from port name
            port_num = None
            port_name = port_data.get('port_name', '').lower()
            
            # Try to extract number from names like "gi7", "gi1/0/7", "GigabitEthernet7"
            # Also include SFP/fiber ports for LLDP monitoring
            import re
            match = re.search(r'(\d+)(?:/0/(\d+))?$', port_name)
            if match:
                # For formats like "gi1/0/7", use the last number
                port_num = int(match.group(2) if match.group(2) else match.group(1))
            elif if_index <= 52:  # Direct mapping for reasonable port counts
                port_num = if_index
            
            # Update the port_number field with the extracted port number
            if port_num:
                ports[if_index]['port_number'] = port_num
                
                # Assign VLAN if we found the port number
                if port_num in vlan_port_map:
                    ports[if_index]['vlan_id'] = vlan_port_map[port_num]
        
        # Convert to PortInfo objects
        port_list = []
        for port_data in ports.values():
            port_list.append(PortInfo(**port_data))
        
        return port_list
