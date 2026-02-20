# MAC Değişikliği Alarm Oluşturmuyor - Debug Rehberi

## Sorun
Kullanıcı raporu: "mac değişimini bildirmiyor direk değiştiriyor alarm yok"

- Empty alarm sorunu çözüldü ✓ (MAC→empty veya empty→MAC artık alarm oluşturmuyor)
- Ama gerçek MAC değişimleri (MAC1→MAC2) de alarm oluşturmuyor ✗

## Olası Nedenler

### 1. Snapshot Hemen Güncellenmiyor
**Senaryo:**
- Port 7'de MAC = `D0:AD:08:E4:12:6A`
- Kullanıcı fiziksel cihazı değiştirir
- Yeni cihaz MAC = `D0:AD:08:E4:12:74`
- SNMP polling olmadan önce snapshot değişmiyor
- Bir sonraki SNMP polling'de değişiklik tespit edilir

**Kontrol:**
```sql
SELECT port_number, mac_address, snapshot_timestamp 
FROM port_snapshot 
WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO')
  AND port_number = 7 
ORDER BY snapshot_timestamp DESC 
LIMIT 5;
```

### 2. Expected MAC Kontrolü Engelliyor
**Senaryo:**
- Port 7'de MAC = `D0:AD:08:E4:12:6A`
- Kullanıcı UI'da expected MAC'i `D0:AD:08:E4:12:74` olarak ayarlıyor
- Fiziksel cihaz değiştiriliyor, yeni MAC = `D0:AD:08:E4:12:74`
- `_detect_mac_changes` çalışıyor
- `_handle_mac_added_or_moved` yeni MAC'i görüyor
- Expected MAC kontrolü: `D0:AD:08:E4:12:74` == `D0:AD:08:E4:12:74` → MATCH!
- Alarm oluşturulmuyor (line 282-313)

**Log Kontrolü:**
```bash
grep "matches expected/registered MAC" logs/snmp_worker.log
```

### 3. _detect_mac_address_change Çalışmıyor
**Senaryo:**
- `_detect_mac_address_change` fonksiyonu hiç çalışmıyor
- Veya çalışıyor ama `both_have_mac` False çıkıyor

**Log Kontrolü:**
```bash
grep "_detect_mac_address_change ÇALIŞTI" logs/snmp_worker.log
```

Eğer bu log yoksa → Fonksiyon hiç çalışmıyor
Eğer varsa → `both_have_mac` değerini kontrol et

### 4. Whitelist Engelliyor
**Senaryo:**
- MAC değişikliği tespit ediliyor
- Alarm oluşturuluyor
- Ama whitelist kontrolü alarm'ı suppress ediyor

**Log Kontrolü:**
```bash
grep "Alarm suppressed (whitelisted)" logs/snmp_worker.log
```

## Debug Adımları

### Adım 1: SNMP Worker Loglarını Temizle ve İzle

```bash
cd /home/runner/work/SW05/SW05/Switchp/snmp_worker

# Mevcut logları yedekle
mv logs/snmp_worker.log logs/snmp_worker.log.backup

# Worker'ı yeniden başlat
pkill -f "python3 main.py"
python3 main.py > worker_output.log 2>&1 &

# Logları izle
tail -f logs/snmp_worker.log | grep -E "(MAC|Alarm|ÇALIŞTI)"
```

### Adım 2: Test Senaryosu Uygula

**Senaryo A: Fiziksel Cihaz Değişimi**
1. Port 7'deki mevcut MAC'i not et
2. Fiziksel cihazı farklı MAC'li bir cihazla değiştir
3. 30-60 saniye bekle (SNMP polling interval)
4. Logları kontrol et

**Senaryo B: UI'dan Expected MAC Değiştirme**
1. Port 7'de mevcut MAC = `D0:AD:08:E4:12:6A`
2. UI'da expected MAC'i `D0:AD:08:E4:12:74` olarak ayarla
3. Fiziksel olarak cihazı DEĞIŞTIRME (aynı cihaz kalsın)
4. SNMP çalıştır
5. Config mismatch alarm bekleniyor

**Senaryo C: Gerçek Cihaz Swap**
1. Port 7'de MAC = `D0:AD:08:E4:12:6A`
2. UI'da expected MAC YOK veya farklı
3. Fiziksel cihazı değiştir, yeni MAC = `D0:AD:08:E4:12:74`
4. SNMP çalıştır
5. MAC değişim alarmı bekleniyor

### Adım 3: Logları Analiz Et

**Beklenen Log Sırası (MAC1→MAC2 Değişiminde):**

```
🔍 MAC DEĞİŞİKLİĞİ KONTROLÜ - SW35-BALO Port 7
   Önceki MAC: 'D0:AD:08:E4:12:6A'
   Şimdiki MAC: 'D0:AD:08:E4:12:74'
   Eşit mi? False
   
🔍 _detect_mac_address_change ÇALIŞTI
   Önceki MAC: 'D0:AD:08:E4:12:6A'
   Şimdiki MAC: 'D0:AD:08:E4:12:74'
   both_have_mac: True

🚨 MAC DEĞİŞİKLİĞİ TESPİT EDİLDİ!
   Change Details: MAC address changed from 'D0:AD:08:E4:12:6A' to 'D0:AD:08:E4:12:74'
   
📢 Alarm oluşturuluyor...
   
✅ Alarm OLUŞTURULDU (ID: 123)
```

**Eğer görmüyorsanız:**

| Log Yok | Sorun | Çözüm |
|---------|-------|-------|
| "MAC DEĞİŞİKLİĞİ KONTROLÜ" yok | Snapshot değişmedi | Snapshot'ları kontrol et |
| "_detect_mac_address_change ÇALIŞTI" yok | Fonksiyon çalışmıyor | Kod akışını kontrol et |
| "both_have_mac: False" | Birisi empty | Önceki/şimdiki MAC'leri kontrol et |
| "Alarm OLUŞTURULDU" yok ama "Alarm oluşturuluyor" var | get_or_create_alarm başarısız | Whitelist/database hatası |

## Kod Akışı

```
SNMP Polling
    ↓
detect_and_record_changes()
    ↓
┌─────────────────────────────────────────┐
│ _detect_mac_changes (eski method)       │ ← Line 91
│   ↓                                     │
│   Removed MACs → _handle_mac_removed    │
│   Added MACs → _handle_mac_added_or_moved │
│                 ↓                       │
│                 Expected MAC check      │ ← Line 282
│                 If match → NO ALARM     │
└─────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────┐
│ _detect_mac_address_change (yeni)       │ ← Line 120
│   ↓                                     │
│   Compare snapshots                     │
│   both_have_mac check                   │ ← Line 926
│   If True → CREATE ALARM                │
└─────────────────────────────────────────┘
```

## Sorun: İki Method Çakışıyor mu?

### Hipotez:
`_detect_mac_changes` çalıştığında MAC değişimini işliyor, ama expected MAC check nedeniyle alarm oluşturmuyor. Sonra `_detect_mac_address_change` çalıştığında... ne oluyor?

### Test:
```sql
-- Change history'de kayıt var mı?
SELECT id, device_id, port_number, change_type, old_mac_address, new_mac_address,
       alarm_created, change_details, change_timestamp
FROM port_change_history
WHERE device_id = (SELECT id FROM snmp_devices WHERE name = 'SW35-BALO')
  AND port_number = 7
ORDER BY change_timestamp DESC
LIMIT 5;
```

Eğer change history var ama alarm_created = 0 → `_detect_mac_changes` işledi ama alarm oluşturmadı.

## Çözüm Önerileri

### Öneri 1: _detect_mac_changes'i Devre Dışı Bırak
`_detect_mac_address_change` zaten snapshot karşılaştırması yapıyor. Eski method'u devre dışı bırakabiliriz.

**Risk:** MAC tracking ve multi-MAC senaryoları bozulabilir.

### Öneri 2: Expected MAC Kontrolünü Sadece Config Mismatch İçin Kullan
`_handle_mac_added_or_moved`'da expected MAC kontrolünü kaldır veya sadece log olarak bırak.

### Öneri 3: _detect_mac_address_change'e Whitelist Skip Ekle
MAC1→MAC2 değişimlerinde skip_whitelist=True kullan.

```python
alarm, is_new = self.db_manager.get_or_create_alarm(
    ...,
    skip_whitelist=True  # MAC swap alarmlarında whitelist'i atla
)
```

## Sonraki Adımlar

1. ✅ Debug logging eklendi
2. ⏳ Kullanıcıdan test sonuçlarını bekle
3. ⏳ Log çıktılarını analiz et
4. ⏳ Gerçek sorunu tespit et
5. ⏳ Uygun çözümü uygula

## Kullanıcıya Soru

Tam olarak ne yapıyorsunuz?

**Senaryo A:** Port 7'deki fiziksel cihazı değiştiriyorum (eski cihazı çıkar, yeni cihaz tak)
**Senaryo B:** UI'da port 7'nin expected MAC'ini değiştiriyorum
**Senaryo C:** Her ikisi de

Hangi senaryoda alarm bekliyorsunuz ama alamıyorsunuz?
