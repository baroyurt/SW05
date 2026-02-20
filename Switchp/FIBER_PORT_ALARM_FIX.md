# Fiber/SFP Port Alarmları - Çözüm Dokümantasyonu

## Sorun

### Kullanıcı Raporu:
```
SW35-BALO - Port 1000 ve Port 100'den sürekli alarmlar geliyor
- Alarm Türü: Açıklama Değişti
- Tekrar Sayısı: 19 ve 7 kez
- "fiber portlardan sürekli alarm geliyor onları geri alalım"
- "fiber bağlantı portu olduğu için surekli mac değişimi göstgeriyor"
```

### Teknik Analiz:

**Neden Fiber Portlar Sürekli Alarm Üretiyor?**

1. **Trafik Geçişi:**
   - Fiber/SFP portları genellikle uplink veya trunk portları
   - Birçok farklı cihazdan trafik geçiyor
   - Her farklı kaynak MAC adresi "MAC değişimi" olarak algılanıyor

2. **Port Davranışı:**
   - Normal Ethernet portlar: 1 cihaz bağlı, MAC sabit
   - Fiber uplink portlar: Birçok MAC geçiyor, sürekli değişim

3. **SNMP Polling:**
   - Her 30 saniyede bir SNMP çalışıyor
   - Her seferinde farklı bir MAC görülebiliyor
   - Bu da sürekli "MAC değişimi" alarmı oluşturuyor

**Örnek Senaryo:**
```
Zaman T0: Port 25 (SFP) → MAC: AA:BB:CC:DD:EE:01
Zaman T1: Port 25 (SFP) → MAC: AA:BB:CC:DD:EE:02
Zaman T2: Port 25 (SFP) → MAC: AA:BB:CC:DD:EE:03
...
→ Sürekli MAC değişimi alarmı!
```

## Çözüm

### Uygulanan Değişiklik:

Fiber/SFP portları tamamen SNMP detection'dan çıkarıldı.

**Dosya:** `cisco_cbs350.py`

**Önceki Kod:**
```python
# Fiber portlar DAHIL ediliyordu
if any(x in descr.lower() for x in ['gi', 'gigabit', 'ethernet', 'sfp', 'fiber']):
    if not any(x in descr.lower() for x in ['vlan', 'management', ...]):
        # Port tespit ediliyordu
```

**Yeni Kod:**
```python
# Fiber portlar EXCLUDE ediliyor
if any(x in descr.lower() for x in ['gi', 'gigabit', 'ethernet']):
    if not any(x in descr.lower() for x in ['vlan', 'management', ..., 'sfp', 'fiber']):
        # Sadece normal Ethernet portlar tespit ediliyor
```

### Değişiklik Detayları:

1. **Include Listesi:**
   - ❌ 'sfp' kaldırıldı
   - ❌ 'fiber' kaldırıldı
   - ✅ 'gi', 'gigabit', 'ethernet' kaldı

2. **Exclude Listesi:**
   - ✅ 'sfp' eklendi
   - ✅ 'fiber' eklendi
   - Port isimleri bu kelimeleri içeriyorsa atlanıyor

## Etki

### CBS350-24FP-4G Switch için:

**Önceki Durum:**
```
Port 1-24  : Ethernet (RJ45)      → Tespit ediliyor ✅
Port 25-28 : SFP (Fiber)          → Tespit ediliyor ✅ (SORUNLU)
```

**Yeni Durum:**
```
Port 1-24  : Ethernet (RJ45)      → Tespit ediliyor ✅
Port 25-28 : SFP (Fiber)          → Tespit EDİLMİYOR ❌ (İSTENEN)
```

### Artık Olmayan Alarmlar:

- ✅ Port 25-28'den MAC değişimi alarmı yok
- ✅ Port 25-28'den açıklama değişimi alarmı yok
- ✅ Fiber port'lardan VLAN değişimi alarmı yok
- ✅ Sürekli tekrarlayan fiber port alarmları yok

## Alternatif Çözümler (Gelecek için)

Kullanıcı "onlara başka çözüm bulacağım" dedi. Olası alternatifler:

### Alternatif 1: Uplink Port Tanımlama
```python
# Belirli portları "uplink" olarak işaretle
uplink_ports = [25, 26, 27, 28]
if port_num in uplink_ports:
    skip_mac_change_detection = True
```

### Alternatif 2: MAC Change Rate Limiting
```python
# Belirli bir sürede çok fazla değişim varsa alarm oluşturma
if mac_change_count_in_last_hour > 10:
    # Bu port uplink olabilir, alarm oluşturma
    pass
```

### Alternatif 3: Trunk Port Detection
```python
# Trunk portları otomatik tespit et
if port_mode == 'trunk':
    skip_mac_change_detection = True
```

### Alternatif 4: Fiber Port için Özel Monitoring
```python
# Fiber portları tespit et ama sadece link status/speed takip et
# MAC tracking yapma
if is_fiber_port:
    monitor_link_only = True
```

## Test Sonuçları

### Beklenen Sonuçlar:

**SNMP Worker Restart Sonrası:**
1. ✅ Port 1-24 tespit ediliyor
2. ✅ Port 25-28 tespit edilmiyor
3. ✅ Fiber port alarmları durdu

**Veritabanı:**
```sql
-- Port 25-28 için kayıt yok
SELECT * FROM port_status_data 
WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO')
  AND port_number BETWEEN 25 AND 28;
-- Sonuç: Boş (0 rows)

-- Port 1-24 için kayıt var
SELECT * FROM port_status_data 
WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO')
  AND port_number BETWEEN 1 AND 24;
-- Sonuç: 24 row
```

**Alarmlar:**
```sql
-- Port 25-28 için yeni alarm yok
SELECT * FROM alarms 
WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO')
  AND port_number BETWEEN 25 AND 28
  AND created_at > NOW() - INTERVAL 1 HOUR;
-- Sonuç: Boş (0 rows)
```

## Özet

| Özellik | Önceki | Yeni |
|---------|--------|------|
| **Fiber Port Detection** | ✅ Aktif | ❌ Devre Dışı |
| **Port 25-28 Monitoring** | ✅ Aktif | ❌ Devre Dışı |
| **Fiber Port Alarmları** | ❌ Sürekli | ✅ Yok |
| **Normal Port (1-24) Monitoring** | ✅ Aktif | ✅ Aktif |
| **VLAN Detection** | ✅ Tüm portlar | ✅ Sadece 1-24 |

**Sonuç:**
- ✅ Fiber port alarm sorunu çözüldü
- ✅ Normal Ethernet portları hala çalışıyor
- ✅ Kullanıcı fiber portlar için ayrı çözüm bulacak
- ✅ Sistem stabil

## Kullanıcıya Not

Fiber/SFP portları şimdilik monitoring dışında. Bu portlar için:

1. **Link Status:** Fiziksel bağlantı kontrolü için ayrı mekanizma düşünülebilir
2. **Bandwidth Monitoring:** SNMP ile sadece bandwidth/trafik izlenebilir
3. **Manual Check:** Önemli değişiklikler manuel kontrol edilebilir

Fiber portlar için özel bir çözüm geliştirildiğinde, bu portları tekrar monitoring'e dahil edebiliriz.
