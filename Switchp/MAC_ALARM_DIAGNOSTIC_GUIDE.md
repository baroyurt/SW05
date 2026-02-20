# MAC Adresi Değişikliği Alarm Sorunları - Tanı Kılavuzu

## Sorun: MAC Değişiyor Ama Alarm Yok

Eğer switch üzerinde MAC adresi değişiyor ama alarm oluşmuyorsa, bu kılavuzu takip edin.

## 1. SNMP Worker Çalışıyor mu?

```bash
# SNMP worker process'ini kontrol et
ps aux | grep snmp_worker

# Son log satırlarını kontrol et
tail -50 logs/snmp_worker.log

# Worker'ı başlat (eğer çalışmıyorsa)
cd snmp_worker
python3 main.py &
```

**Beklenen:** Son 1-2 dakika içinde log girdileri olmalı

## 2. MAC Değişikliği Tespit Ediliyor mu?

Log dosyasını kontrol edin:

```bash
tail -100 logs/snmp_worker.log | grep "MAC DEĞİŞİKLİĞİ KONTROLÜ" -A 5
```

**Beklenen Çıktı (MAC Değişmişse):**
```
🔍 MAC DEĞİŞİKLİĞİ KONTROLÜ - SW35-BALO Port 7
   Önceki MAC: 'D0:AD:08:E4:12:74'
   Şimdiki MAC: 'D0:AD:08:E4:12:6A'
   Eşit mi? False
🚨 MAC DEĞİŞİKLİĞİ TESPİT EDİLDİ!
```

**Eğer "Eşit mi? True" diyorsa:**
- SNMP aynı MAC adresini görüyor
- Fiziksel cihaz gerçekten değişti mi kontrol edin
- Snapshot'lar doğru güncellenmiyor olabilir

## 3. Yapılandırma Uyuşmazlığı Tespit Ediliyor mu?

```bash
tail -100 logs/snmp_worker.log | grep "MAC CONFIGURATION MISMATCH" -A 15
```

**Beklenen Çıktı:**
```
⚠️ ⚠️ ⚠️  MAC CONFIGURATION MISMATCH DETECTED  ⚠️ ⚠️ ⚠️
Device: SW35-BALO, Port: 7
Expected MAC: AA:AA:AA:E4:12:6A
Actual MAC(s): D0:AD:08:E4:12:6A

⚠️ WHITELIST ATLATILDI (skip_whitelist=True): SW35-BALO port 7 MAC D0:AD:08:E4:12:6A

🚨 YENİ ALARM OLUŞTURULDU!
   Alarm ID: 123
   skip_whitelist was: True
```

## 4. Whitelist Alarm'ı Engelliyor mu?

```bash
tail -100 logs/snmp_worker.log | grep "Alarm suppressed (whitelisted)"
```

**Eğer bu mesaj görünüyorsa:**
- ❌ Whitelist hala alarm'ı engelliyor
- ✅ Skip_whitelist düzeltmesi çalışmıyor

**Eğer "WHITELIST ATLATILDI" görünüyorsa:**
- ✅ Skip_whitelist düzgün çalışıyor
- Alarm oluşturulmalı

## 5. Alarm Veritabanına Kaydediliyor mu?

```sql
SELECT id, device_id, port_number, alarm_type, severity, status, 
       title, old_value, new_value, created_at 
FROM alarms 
WHERE port_number = 7 
  AND status = 'ACTIVE'
ORDER BY id DESC 
LIMIT 5;
```

**Eğer alarm varsa:**
- ✅ Alarm oluşturulmuş
- ❌ UI'da gösterilmiyor - PHP display sorunu

**Eğer alarm yoksa:**
- ❌ Alarm oluşturulmamış
- Logları kontrol edin, hangi aşamada başarısız oluyor?

## 6. UI'da Alarm Görünüyor mu?

```bash
# port_alarms.php sayfasını kontrol et
curl http://localhost/Switchp/port_alarms.php 2>/dev/null | grep "Port 7"
```

Veya tarayıcıdan: `http://yourserver/Switchp/port_alarms.php`

## Olası Senaryolar ve Çözümler

### Senaryo A: "MAC değişmedi" Logu Görünüyor

**Sorun:** Snapshot'lar aynı MAC'i gösteriyor
**Çözüm:**
1. Snapshot tablosunu kontrol edin:
```sql
SELECT port_number, mac_address, snapshot_timestamp 
FROM port_snapshot 
WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO')
  AND port_number = 7 
ORDER BY snapshot_timestamp DESC 
LIMIT 5;
```

2. Eğer tüm snapshot'lar aynı MAC'e sahipse:
   - SNMP gerçekten aynı MAC'i görüyor
   - Fiziksel cihaz değişmedi veya MAC aynı kaldı

### Senaryo B: "Alarm suppressed (whitelisted)" Görünüyor

**Sorun:** Whitelist hala alarm'ı engelliyor
**Çözüm:**
1. Acknowledged_port_mac tablosunu kontrol et:
```sql
SELECT * FROM acknowledged_port_mac 
WHERE device_name = 'SW35-BALO' AND port_number = 7;
```

2. Whitelist kaydını sil:
```sql
DELETE FROM acknowledged_port_mac 
WHERE device_name = 'SW35-BALO' AND port_number = 7;
```

3. Tekrar SNMP çalıştır

### Senaryo C: Config Mismatch Tespit Edilmiyor

**Sorun:** Expected MAC ayarlanmamış
**Çözüm:**
1. Ports tablosunu kontrol et:
```sql
SELECT p.port_no, p.mac, s.name 
FROM ports p 
JOIN switches s ON p.switch_id = s.id 
WHERE s.name = 'SW35-BALO' AND p.port_no = 7;
```

2. MAC adresi NULL veya boşsa, UI'dan MAC adresini kaydedin

### Senaryo D: Alarm Oluşuyor Ama UI'da Görünmüyor

**Sorun:** PHP alarm display sorunu
**Çözüm:**
1. Tarayıcı console'unu kontrol edin (F12)
2. Network tab'da port_alarms.php yanıtını inceleyin
3. JavaScript hatalarını kontrol edin

## Önemli Notlar

### İki Farklı MAC Değişikliği Türü:

**1. Fiziksel Cihaz Değişikliği (Snapshot Karşılaştırması)**
- SNMP önceki tarama: MAC = 12:74
- SNMP şimdiki tarama: MAC = 12:6a  
- **Fonksiyon:** `_detect_mac_address_change`
- **Alarm:** Eski Değer: 12:74, Yeni Değer: 12:6a

**2. Yapılandırma Uyuşmazlığı (Expected vs Actual)**
- Kullanıcı beklediği: MAC = AA:AA
- SNMP gördüğü: MAC = D0:AD
- **Fonksiyon:** `_detect_mac_config_mismatch`  
- **Alarm:** Eski Değer: AA:AA, Yeni Değer: D0:AD
- **ÖNEMLİ:** skip_whitelist=True (whitelist engellemez)

## Hızlı Test

MAC değişikliği alarm'ını test etmek için:

```bash
# 1. SNMP worker'ı durdur
pkill -f "python3 main.py"

# 2. Port 7 için snapshot'ı sil (test için)
mysql -u root switchdb -e "DELETE FROM port_snapshot WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO') AND port_number = 7;"

# 3. SNMP worker'ı başlat ve logları izle
cd snmp_worker
python3 main.py 2>&1 | grep -E "(MAC|Alarm)" &

# 4. 30 saniye bekle (polling interval)
# 5. İlk snapshot oluşturulacak

# 6. Port'taki cihazı fiziksel olarak değiştir
# 7. 30 saniye daha bekle
# 8. MAC değişikliği tespit edilmeli
```

## Kod Akışı

```
Polling Engine
    ↓
detect_and_record_changes()
    ↓
┌─────────────────────────────────────────┐
│ 1. Get previous snapshot                │
│ 2. _detect_mac_changes (old method)     │ ← Whitelist check yapabilir
│ 3. _detect_mac_address_change (new)     │ ← Snapshot comparison
│ 4. _detect_mac_config_mismatch (new)    │ ← skip_whitelist=True
│ 5. Create new snapshot                  │
└─────────────────────────────────────────┘
    ↓
get_or_create_alarm(skip_whitelist=?)
    ↓
┌─────────────────────────────────────────┐
│ if skip_whitelist:                      │
│    ⚠️ WHITELIST ATLATILDI               │
│ else:                                   │
│    Check whitelist → suppress if found  │
└─────────────────────────────────────────┘
    ↓
🚨 YENİ ALARM OLUŞTURULDU!
```

## Sonraki Adımlar

1. ✅ SNMP worker'ı çalıştırın
2. ✅ Port 7'deki cihazı değiştirin veya expected MAC'i UI'da güncelleyin
3. ✅ 30-60 saniye bekleyin
4. ✅ Logları kontrol edin - yukarıdaki mesajları arayın
5. ✅ Hangi aşamada takıldığını belirleyin
6. ✅ Log çıktılarını bana gönderin

Detaylı loglar ile sorunu kesin olarak tespit edip çözebiliriz! 🔍
