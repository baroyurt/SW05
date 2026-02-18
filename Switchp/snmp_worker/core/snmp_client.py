"""
SNMP Client for querying network devices.
Supports both SNMP v2c and v3.

NOTE: This module uses pysnmp-lextudio, which is a maintained fork of pysnmp
that supports Python 3.12+. The API is compatible with the original pysnmp.
"""

from typing import Optional, Dict, List, Tuple, Any
import logging

# SNMP imports - wrapped in try/except for graceful degradation
# Using explicit imports for pysnmp-lextudio 5.x compatibility
try:
    from pysnmp.hlapi import (
        UsmUserData, CommunityData,
        usmHMACSHAAuthProtocol, usmHMACMD5AuthProtocol,
        usmHMAC128SHA224AuthProtocol, usmHMAC192SHA256AuthProtocol,
        usmHMAC256SHA384AuthProtocol, usmHMAC384SHA512AuthProtocol,
        usmAesCfb128Protocol, usmAesCfb192Protocol, usmAesCfb256Protocol,
        usmDESPrivProtocol,
        SnmpEngine, UdpTransportTarget, ContextData,
        ObjectType, ObjectIdentity, getCmd, bulkCmd,
        nextCmd
    )
    SNMP_AVAILABLE = True
except ImportError as e:
    SNMP_AVAILABLE = False
    import_error = str(e)
    logging.warning(f"pysnmp not available - SNMP functionality will be limited. Error: {import_error}")
    logging.warning("To fix: pip install pysnmp-lextudio")
except Exception as e:
    SNMP_AVAILABLE = False
    import_error = str(e)
    logging.error(f"Unexpected error importing pysnmp: {import_error}")
    logging.error("To fix: pip install --force-reinstall pysnmp-lextudio")


class SNMPClient:
    """
    SNMP client wrapper for pysnmp.
    Provides simplified interface for SNMP operations.
    """
    
    def __init__(
        self,
        host: str,
        port: int = 161,
        version: str = "2c",
        community: str = "public",
        timeout: int = 2,  # Test script'te 2 saniye
        retries: int = 1,  # Test script'te 1 deneme
        username: Optional[str] = None,
        auth_protocol: Optional[str] = None,
        auth_password: Optional[str] = None,
        priv_protocol: Optional[str] = None,
        priv_password: Optional[str] = None,
        engine_id: Optional[str] = None
    ):
        """
        Initialize SNMP client.
        
        Args:
            host: Target device IP or hostname
            port: SNMP port (default 161)
            version: SNMP version ("2c" or "3")
            community: SNMP community string for v2c
            timeout: Timeout in seconds (default 2)
            retries: Number of retries (default 1)
            username: SNMPv3 username
            auth_protocol: SNMPv3 auth protocol (SHA or MD5)
            auth_password: SNMPv3 auth password
            priv_protocol: SNMPv3 privacy protocol (AES or DES)
            priv_password: SNMPv3 privacy password
            engine_id: SNMPv3 engine ID (hex string, optional)
        """
        self.host = host
        self.port = port
        self.version = version
        self.community = community
        self.timeout = timeout
        self.retries = retries
        
        # SNMPv3 parameters
        self.username = username
        self.auth_protocol = auth_protocol
        self.auth_password = auth_password
        self.priv_protocol = priv_protocol
        self.priv_password = priv_password
        self.engine_id = engine_id
        
        self.logger = logging.getLogger('snmp_worker.snmp_client')
        
        # Setup authentication data
        self._auth_data = self._setup_auth()
        
        # Setup transport target
        self._transport = UdpTransportTarget(
            (host, port),
            timeout=timeout,
            retries=retries
        )
        self._engine = SnmpEngine()
        self._context = ContextData()
    
    def _setup_auth(self):
        """Setup authentication data based on SNMP version."""
        if not SNMP_AVAILABLE:
            return None
            
        if self.version == "3":
            # ★★★ TEST SCRIPT'İ İLE %100 UYUMLU SNMPv3 AUTH ★★★
            
            # Auth Protocol Mapping
            auth_proto = usmHMACSHAAuthProtocol  # DEFAULT: SHA
            if self.auth_protocol:
                auth_proto_upper = self.auth_protocol.upper()
                if auth_proto_upper == "MD5":
                    auth_proto = usmHMACMD5AuthProtocol
                elif auth_proto_upper == "SHA224":
                    auth_proto = usmHMAC128SHA224AuthProtocol
                elif auth_proto_upper == "SHA256":
                    auth_proto = usmHMAC192SHA256AuthProtocol
                elif auth_proto_upper == "SHA384":
                    auth_proto = usmHMAC256SHA384AuthProtocol
                elif auth_proto_upper == "SHA512":
                    auth_proto = usmHMAC384SHA512AuthProtocol
                # SHA varsayılan, başka bir değişiklik yapma
            
            # Priv Protocol Mapping
            priv_proto = usmAesCfb128Protocol  # DEFAULT: AES128
            if self.priv_protocol:
                priv_proto_upper = self.priv_protocol.upper()
                if priv_proto_upper == "DES":
                    priv_proto = usmDESPrivProtocol
                elif priv_proto_upper in ["AES192", "AES-192"]:
                    priv_proto = usmAesCfb192Protocol
                elif priv_proto_upper in ["AES256", "AES-256"]:
                    priv_proto = usmAesCfb256Protocol
                # AES128 varsayılan
            
            # ★★★ CRITICAL FIX: Doğru parametre sırası - test script'i ile aynı ★★★
            # UsmUserData(userName, authKey, privKey, authProtocol, privProtocol)
            return UsmUserData(
                self.username or 'snmpuser',
                self.auth_password or 'AuthPass123',  # 2. parametre: authKey
                self.priv_password or 'PrivPass123',  # 3. parametre: privKey
                authProtocol=auth_proto,
                privProtocol=priv_proto
            )
        else:
            # SNMPv2c
            return CommunityData(self.community, mpModel=1)
    
    def get(self, oid: str) -> Optional[Tuple[str, Any]]:
        """
        Perform SNMP GET operation.
        
        Args:
            oid: OID to query
            
        Returns:
            Tuple of (oid, value) or None on error
        """
        if not SNMP_AVAILABLE:
            self.logger.error("SNMP library not available - cannot perform SNMP GET operation")
            self.logger.error("Install pysnmp with: pip install pysnmp-lextudio")
            return None
            
        try:
            iterator = getCmd(
                self._engine,
                self._auth_data,
                self._transport,
                self._context,
                ObjectType(ObjectIdentity(oid))
            )
            
            errorIndication, errorStatus, errorIndex, varBinds = next(iterator)
            
            if errorIndication:
                self.logger.error(f"SNMP GET error: {errorIndication}")
                return None
            elif errorStatus:
                self.logger.error(f"SNMP GET error: {errorStatus.prettyPrint()}")
                return None
            else:
                for varBind in varBinds:
                    return str(varBind[0]), varBind[1]
        
        except Exception as e:
            self.logger.error(f"Exception during SNMP GET: {e}")
            return None
    
    def get_bulk(self, oid: str, max_repetitions: int = 50) -> List[Tuple[str, Any]]:
        """
        Perform SNMP GETBULK operation (walk).
        
        Args:
            oid: Starting OID
            max_repetitions: Maximum number of repetitions
            
        Returns:
            List of (oid, value) tuples
        """
        results = []
        
        try:
            iterator = bulkCmd(
                self._engine,
                self._auth_data,
                self._transport,
                self._context,
                0,  # nonRepeaters
                max_repetitions,
                ObjectType(ObjectIdentity(oid)),
                lexicographicMode=False
            )
            
            for errorIndication, errorStatus, errorIndex, varBinds in iterator:
                if errorIndication:
                    self.logger.error(f"SNMP GETBULK error: {errorIndication}")
                    break
                elif errorStatus:
                    self.logger.error(f"SNMP GETBULK error: {errorStatus.prettyPrint()}")
                    break
                else:
                    for varBind in varBinds:
                        oid_str = str(varBind[0])
                        value = varBind[1]
                        results.append((oid_str, value))
        
        except Exception as e:
            self.logger.error(f"Exception during SNMP GETBULK: {e}")
        
        return results
    
    def get_multiple(self, oids: List[str]) -> Dict[str, Any]:
        """
        Get multiple OIDs in a single request.
        
        Args:
            oids: List of OIDs to query
            
        Returns:
            Dictionary mapping OID to value
        """
        results = {}
        
        try:
            object_types = [ObjectType(ObjectIdentity(oid)) for oid in oids]
            
            iterator = getCmd(
                self._engine,
                self._auth_data,
                self._transport,
                self._context,
                *object_types
            )
            
            errorIndication, errorStatus, errorIndex, varBinds = next(iterator)
            
            if errorIndication:
                self.logger.error(f"SNMP GET error: {errorIndication}")
                return results
            elif errorStatus:
                self.logger.error(f"SNMP GET error: {errorStatus.prettyPrint()}")
                return results
            
            for varBind in varBinds:
                oid_str = str(varBind[0])
                value = varBind[1]
                results[oid_str] = value
        
        except Exception as e:
            self.logger.error(f"Exception during SNMP GET multiple: {e}")
        
        return results
    
    def test_connection(self) -> bool:
        """
        Test SNMP connectivity.
        
        Returns:
            True if connection successful, False otherwise
        """
        result = self.get("1.3.6.1.2.1.1.1.0")  # sysDescr
        if result:
            self.logger.info(f"SNMP connection test successful")
            return True
        return False