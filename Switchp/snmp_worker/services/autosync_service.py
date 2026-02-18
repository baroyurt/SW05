"""
Automatic synchronization service.
Syncs SNMP worker data to main switches table automatically.
"""

import logging
from typing import Dict, Any
from datetime import datetime
from sqlalchemy.orm import Session
from sqlalchemy import text

from models.database import SNMPDevice, PortStatusData
from core.database_manager import DatabaseManager


class AutoSyncService:
    """
    Automatically synchronizes SNMP worker data to main switches database.
    This service runs after each polling cycle to keep the main database updated.
    """
    
    def __init__(self, db_manager: DatabaseManager):
        """
        Initialize auto sync service.
        
        Args:
            db_manager: Database manager
        """
        self.db_manager = db_manager
        self.logger = logging.getLogger('snmp_worker.autosync')
        
        self.logger.info("Auto Sync Service initialized")
    
    def sync_all_devices(self, session: Session) -> Dict[str, Any]:
        """
        Sync all active SNMP devices to main switches table.
        
        Args:
            session: Database session
            
        Returns:
            Dict with sync results
        """
        synced_count = 0
        error_count = 0
        errors = []
        
        try:
            # Get all active SNMP devices
            devices = session.query(SNMPDevice).filter(
                SNMPDevice.enabled == True
            ).all()
            
            self.logger.info(f"Starting automatic sync for {len(devices)} device(s)")
            
            for device in devices:
                try:
                    self._sync_device(session, device)
                    synced_count += 1
                except Exception as e:
                    error_count += 1
                    error_msg = f"Error syncing {device.name}: {str(e)}"
                    errors.append(error_msg)
                    self.logger.error(error_msg)
            
            # Commit all changes
            session.commit()
            
            if synced_count > 0:
                self.logger.info(
                    f"Auto sync complete: {synced_count} device(s) synchronized, "
                    f"{error_count} error(s)"
                )
            
            return {
                'success': True,
                'synced_count': synced_count,
                'error_count': error_count,
                'errors': errors
            }
            
        except Exception as e:
            session.rollback()
            error_msg = f"Fatal error during auto sync: {str(e)}"
            self.logger.error(error_msg)
            return {
                'success': False,
                'synced_count': synced_count,
                'error_count': error_count + 1,
                'errors': errors + [error_msg]
            }
    
    def _sync_device(self, session: Session, device: SNMPDevice) -> None:
        """
        Sync a single device to main switches table.
        
        Args:
            session: Database session
            device: SNMP device to sync
        """
        # Check if switch exists in main table
        result = session.execute(
            text("SELECT id FROM switches WHERE ip = :ip"),
            {"ip": device.ip_address}
        ).fetchone()
        
        if result:
            # Update existing switch
            switch_id = result[0]
            
            status = 'online' if device.status.value.upper() == 'ONLINE' else 'offline'
            
            session.execute(
                text("""
                    UPDATE switches 
                    SET name = :name, 
                        brand = :brand, 
                        model = :model, 
                        ports = :ports, 
                        status = :status
                    WHERE id = :id
                """),
                {
                    "name": device.name,
                    "brand": device.vendor,
                    "model": device.model,
                    "ports": device.total_ports,
                    "status": status,
                    "id": switch_id
                }
            )
            
            self.logger.debug(f"Updated switch: {device.name} (ID: {switch_id})")
            
        else:
            # Insert new switch
            result = session.execute(
                text("""
                    INSERT INTO switches (name, brand, model, ports, ip, status)
                    VALUES (:name, :brand, :model, :ports, :ip, 'online')
                """),
                {
                    "name": device.name,
                    "brand": device.vendor,
                    "model": device.model,
                    "ports": device.total_ports,
                    "ip": device.ip_address
                }
            )
            
            switch_id = result.lastrowid
            
            self.logger.info(f"Created new switch: {device.name} (ID: {switch_id})")
        
        # Sync port data
        self._sync_ports(session, device, switch_id)
    
    def _sync_ports(self, session: Session, device: SNMPDevice, switch_id: int) -> None:
        """
        Sync port data for a device.
        
        Args:
            session: Database session
            device: SNMP device
            switch_id: Main switches table ID
        """
        # Get latest port data
        latest_ports = session.query(PortStatusData).filter(
            PortStatusData.device_id == device.id
        ).filter(
            PortStatusData.poll_timestamp == session.query(
                PortStatusData.poll_timestamp
            ).filter(
                PortStatusData.device_id == device.id
            ).order_by(
                PortStatusData.poll_timestamp.desc()
            ).limit(1).scalar_subquery()
        ).all()
        
        ports_synced = 0
        
        for port in latest_ports:
            try:
                # Check if port exists
                result = session.execute(
                    text("SELECT id FROM ports WHERE switch_id = :switch_id AND port_no = :port_no"),
                    {"switch_id": switch_id, "port_no": port.port_number}
                ).fetchone()
                
                # Prepare port data
                device_name = port.port_alias if port.port_alias else port.port_name
                port_type = 'DEVICE' if port.oper_status.value == 'up' else 'EMPTY'
                mac_address = port.mac_address if port.mac_address else ''
                
                if result:
                    # Update existing port
                    port_id = result[0]
                    
                    session.execute(
                        text("""
                            UPDATE ports 
                            SET type = :type, 
                                device = :device, 
                                ip = '', 
                                mac = :mac
                            WHERE id = :id
                        """),
                        {
                            "type": port_type,
                            "device": device_name,
                            "mac": mac_address,
                            "id": port_id
                        }
                    )
                else:
                    # Insert new port
                    session.execute(
                        text("""
                            INSERT INTO ports (switch_id, port_no, type, device, mac)
                            VALUES (:switch_id, :port_no, :type, :device, :mac)
                        """),
                        {
                            "switch_id": switch_id,
                            "port_no": port.port_number,
                            "type": port_type,
                            "device": device_name,
                            "mac": mac_address
                        }
                    )
                
                ports_synced += 1
                
            except Exception as e:
                self.logger.warning(f"Error syncing port {port.port_number} on {device.name}: {e}")
        
        if ports_synced > 0:
            self.logger.debug(f"Synced {ports_synced} port(s) for {device.name}")
