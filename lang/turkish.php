<?php
/**
 * Cloudflare Manager - Turkish Language File
 */

// Genel
$_LANG['cloudflare_manager'] = "Cloudflare Yöneticisi";
$_LANG['error'] = "Hata";
$_LANG['success'] = "Başarılı";
$_LANG['save'] = "Kaydet";
$_LANG['cancel'] = "İptal";
$_LANG['close'] = "Kapat";
$_LANG['edit'] = "Düzenle";
$_LANG['delete'] = "Sil";
$_LANG['yes'] = "Evet";
$_LANG['no'] = "Hayır";
$_LANG['automatic'] = "Otomatik";

// Karşılama Modalı
$_LANG['welcome_to_cloudflare_manager'] = "Cloudflare Yöneticisine Hoş Geldiniz";
$_LANG['about'] = "Hakkında";
$_LANG['got_it'] = "Anladım!";
$_LANG['dont_show_again'] = "Bir daha gösterme";

// Ana Menü
$_LANG['overview'] = "Genel Bakış";
$_LANG['domains'] = "Alan Adları";
$_LANG['dns_management'] = "DNS Yönetimi";
$_LANG['settings'] = "Ayarlar";

// Domain Listesi
$_LANG['cloudflare_domains'] = "Cloudflare Domainleri";
$_LANG['domain'] = "Domain";
$_LANG['domain_name'] = "Domain Adı";
$_LANG['status'] = "Durum";
$_LANG['active'] = "Aktif";
$_LANG['inactive'] = "Pasif";
$_LANG['created_on'] = "Oluşturulma Tarihi";
$_LANG['modified_on'] = "Değiştirilme Tarihi";
$_LANG['expiry_date'] = "Bitiş Tarihi";
$_LANG['ssl_status'] = "SSL Durumu";
$_LANG['plan_type'] = "Plan Tipi";
$_LANG['no_domains_found'] = "Hiç domain bulunamadı.";
$_LANG['select_domain'] = "Domain Seçin";
$_LANG['select_domain_to_view_dns'] = "DNS kayıtlarını görüntülemek için lütfen bir domain seçin.";
$_LANG['your_domains'] = "Domainleriniz";
$_LANG['registration_date'] = "Kayıt Tarihi";
$_LANG['domain_not_found'] = "Domain bulunamadı.";
$_LANG['registrar'] = "Kayıt Şirketi";
$_LANG['nameservers'] = "Nameserverlar";
$_LANG['original_registrar'] = "Orijinal Kayıt Şirketi";
$_LANG['original_dnshost'] = "Orijinal DNS Sağlayıcısı";

// DNS Yönetimi
$_LANG['dns_records'] = "DNS Kayıtları";
$_LANG['dns_records_for'] = "DNS Kayıtları:";
$_LANG['type'] = "Tür";
$_LANG['name'] = "Ad";
$_LANG['content'] = "İçerik";
$_LANG['ttl'] = "TTL";
$_LANG['proxied'] = "Proxy";
$_LANG['priority'] = "Öncelik";
$_LANG['priority_tip'] = "MX ve SRV kayıtları için kullanılır (düşük sayı = yüksek öncelik)";
$_LANG['add_new_record'] = "Yeni Kayıt Ekle";
$_LANG['add_dns_record'] = "DNS Kaydı Ekle";
$_LANG['edit_dns_record'] = "DNS Kaydı Düzenle";
$_LANG['dns_name_tip'] = "@ kullanarak kök domaini ifade edebilirsiniz.";
$_LANG['proxy_tip'] = "Proxy etkinleştirilirse, Cloudflare trafiği kendi sunucuları üzerinden geçirir.";
$_LANG['dns_record_added'] = "DNS kaydı başarıyla eklendi.";
$_LANG['dns_record_updated'] = "DNS kaydı başarıyla güncellendi.";
$_LANG['dns_record_deleted'] = "DNS kaydı başarıyla silindi.";
$_LANG['dns_record_add_error'] = "DNS kaydı eklenirken hata oluştu";
$_LANG['dns_record_update_error'] = "DNS kaydı güncellenirken hata oluştu";
$_LANG['dns_record_delete_error'] = "DNS kaydı silinirken hata oluştu";
$_LANG['confirm_delete_dns'] = "Bu DNS kaydını silmek istediğinizden emin misiniz?";
$_LANG['no_dns_records'] = "Hiç DNS kaydı bulunamadı.";

// İşlemler
$_LANG['actions'] = "İşlemler";
$_LANG['details'] = "Detaylar";
$_LANG['details_for'] = "Detaylar:";
$_LANG['purge_cache'] = "Önbelleği Temizle";
$_LANG['cache_purged'] = "Önbellek başarıyla temizlendi.";
$_LANG['cache_purge_error'] = "Önbellek temizlenirken hata oluştu";
$_LANG['cache_cleared'] = "Önbellek başarıyla temizlendi.";
$_LANG['cache_clear_error'] = "Önbellek temizlenirken hata oluştu";
$_LANG['confirm_purge_cache'] = "Önbelleği tamamen temizlemek istediğinizden emin misiniz? Bu işlem geri alınamaz!";
$_LANG['sync_domains'] = "Domainleri Senkronize Et";
$_LANG['sync_completed'] = "%s domain başarıyla senkronize edildi.";
$_LANG['sync_error'] = "Domain senkronizasyonu sırasında hata oluştu";

// Ayarlar
$_LANG['client_permissions'] = "Müşteri İzinleri";
$_LANG['client_permissions_desc'] = "Müşterilerin Cloudflare Manager'da hangi özellikleri görebileceğini seçin.";
$_LANG['view_domain_details'] = "Domain Detaylarını Görüntüleme";
$_LANG['view_dns_records'] = "DNS Kayıtlarını Görüntüleme";
$_LANG['view_ssl_status'] = "SSL Durumunu Görüntüleme";
$_LANG['view_cache_status'] = "Önbellek Durumunu Görüntüleme";
$_LANG['save_changes'] = "Değişiklikleri Kaydet";
$_LANG['settings_updated'] = "Ayarlar başarıyla güncellendi.";
$_LANG['settings_update_error'] = "Ayarlar güncellenirken hata oluştu";
$_LANG['database_info'] = "Veritabanı Bilgileri";
$_LANG['total_domains'] = "Toplam Domainler";
$_LANG['total_dns_records'] = "Toplam DNS Kayıtları";
$_LANG['cache_items'] = "Önbellek Öğeleri";
$_LANG['settings_count'] = "Ayar Sayısı";
$_LANG['database_error'] = "Veritabanı hatası";
$_LANG['about_module'] = "Modül Hakkında";
$_LANG['module_description'] = "Bu modül, Cloudflare hesabınızdaki domainleri ve DNS kayıtlarını yönetmenizi sağlar.";
$_LANG['module_description_detailed'] = "Cloudflare Yöneticisi, WHMCS kurulumunuzu Cloudflare ile sorunsuz bir şekilde entegre ederek domainlerinizi, DNS kayıtlarınızı, SSL ayarlarınızı ve önbelleğinizi tek bir panelden yönetmenize olanak tanır. Müşterilerine verimli Cloudflare yönetimi sunmak isteyen hosting şirketleri ve domain satıcıları için uygundur.";
$_LANG['module_version'] = "Modül Versiyonu";
$_LANG['module_author'] = "Geliştirici";
$_LANG['developed_by'] = "Geliştiren";

// Modül Özellikleri
$_LANG['key_features'] = "Temel Özellikler";
$_LANG['feature_domain_management'] = "Tüm Cloudflare domainlerinizi kolayca görüntüleyin ve senkronize edin";
$_LANG['feature_dns_management'] = "Tüm kayıt tiplerini destekleyen eksiksiz DNS yönetimi";
$_LANG['feature_ssl_monitoring'] = "Tüm domainlerde SSL sertifika durumunu izleyin";
$_LANG['feature_cache_purging'] = "Gerektiğinde tek tıklamayla önbelleği temizleyin";
$_LANG['feature_client_access'] = "Müşterilerin kendi domainlerini yönetmesi için erişim";

// Başlangıç
$_LANG['getting_started'] = "Başlarken";
$_LANG['getting_started_sync'] = "Cloudflare'dan domainlerinizi senkronize ederek başlayın";
$_LANG['getting_started_dns'] = "Her domain için DNS kayıtlarını yönetin";
$_LANG['getting_started_customize'] = "Ayarlarda müşteri izinlerini özelleştirin";

// Zaman Birimleri
$_LANG['minutes'] = "Dakika";
$_LANG['hours'] = "Saat";
$_LANG['hour'] = "Saat";
$_LANG['day'] = "Gün";

// API ve Bağlantı
$_LANG['api_connection_success'] = "Cloudflare API bağlantısı başarılı!";
$_LANG['api_connection_error'] = "Cloudflare API bağlantı hatası";
$_LANG['check_api_credentials'] = "Lütfen API kimlik bilgilerinizi kontrol edin.";
$_LANG['error_fetching_domains'] = "Domainler alınırken hata oluştu";
$_LANG['error_fetching_dns'] = "DNS kayıtları alınırken hata oluştu";
$_LANG['error_fetching_details'] = "Domain detayları alınırken hata oluştu";
$_LANG['csrf_error'] = "Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.";

// Performans
$_LANG['performance_settings'] = "Performans Ayarları";
$_LANG['api_cache_enabled'] = "API Yanıt Önbelleğini Etkinleştir";
$_LANG['api_cache_desc'] = "API yanıtlarını önbelleğe almak performansı artırır ancak biraz eskimiş bilgiler gösterebilir";
$_LANG['cache_expiry'] = "Önbellek Süresi (saniye)";
$_LANG['cache_expiry_help'] = "API önbelleğinin ne kadar süre saklanacağı (en az 60 saniye)";
$_LANG['clear_all_cache'] = "Tüm Önbelleği Temizle";
$_LANG['confirm_clear_cache'] = "Tüm önbelleği temizlemek istediğinizden emin misiniz?";

// Analitik ve İstatistikler
$_LANG['traffic_stats'] = "Trafik İstatistikleri";
$_LANG['security_stats'] = "Güvenlik İstatistikleri";
$_LANG['analytics_data'] = "Analitik Verileri";
$_LANG['total_requests'] = "Toplam İstekler";
$_LANG['unique_visitors'] = "Tekil Ziyaretçiler";
$_LANG['page_views'] = "Sayfa Görüntülemeleri";
$_LANG['security_threats'] = "Güvenlik Tehditleri";
$_LANG['data_last_updated'] = "Veri Son Güncelleme";
$_LANG['analytics_period'] = "Analitik Dönemi";
$_LANG['last_24_hours'] = "Son 24 Saat";
$_LANG['last_7_days'] = "Son 7 Gün";
$_LANG['last_30_days'] = "Son 30 Gün";
$_LANG['by_country'] = "Ülkeye Göre";
$_LANG['browser_stats'] = "Tarayıcı İstatistikleri";

// Müşteri Tarafı
$_LANG['back_to_domains'] = "Domainlere Geri Dön";
$_LANG['cache_management'] = "Önbellek Yönetimi";
$_LANG['cache_management_desc'] = "Web sitenizin içeriğini güncellediyseniz, ziyaretçilerin en son sürümü görmesini sağlamak için Cloudflare önbelleğini temizleyebilirsiniz.";

// Hata Ayıklama
$_LANG['troubleshooting'] = "Sorun Giderme";
$_LANG['tip_api_key'] = "Global API Key doğru ve tam uzunlukta olmalıdır.";
$_LANG['tip_api_token'] = "Alternatif olarak, email alanını boş bırakıp API Token kullanabilirsiniz.";
$_LANG['tip_token_permissions'] = "API Token kullanıyorsanız, 'Zone DNS (Edit)' yetkisi olduğundan emin olun.";
$_LANG['how_to_get_api_key'] = "Global API Key Nasıl Alınır";
$_LANG['login_to_cloudflare'] = "Cloudflare hesabınıza giriş yapın";
$_LANG['go_to_profile'] = "Sağ üst köşede profil simgesine tıklayın ve 'My Profile' seçin";
$_LANG['go_to_api_tokens'] = "'API Tokens' sekmesine gidin";
$_LANG['view_global_key'] = "'Global API Key' bölümünde 'View' tıklayın ve şifrenizi onaylayın";
$_LANG['how_to_get_api_token'] = "API Token Nasıl Oluşturulur";
$_LANG['create_token'] = "'Create Token' butonuna tıklayın";
$_LANG['use_edit_zone_template'] = "'Edit zone DNS' template'ini seçin";
$_LANG['copy_token'] = "Oluşturulan token'ı kopyalayın";

// Form Doğrulama
$_LANG['missing_required_fields'] = "Lütfen tüm zorunlu alanları doldurun";
$_LANG['invalid_ipv4_address'] = "Geçersiz IPv4 adres formatı";
$_LANG['invalid_ipv6_address'] = "Geçersiz IPv6 adres formatı";
$_LANG['invalid_hostname'] = "Geçersiz sunucu adı formatı";
$_LANG['dns_records_synced'] = "DNS kayıtları başarıyla senkronize edildi";
$_LANG['dns_sync_error'] = "DNS kayıtları senkronize edilirken hata oluştu";

// Registrar
$_LANG['domain_transfer'] = "Domain Transfer";
$_LANG['domain_registration'] = "Domain Kayıt";
$_LANG['domain_transfer_initiated'] = "Domain transferi başlatıldı";
$_LANG['domain_transfer_error'] = "Domain transferi hatası";
$_LANG['domain_registered'] = "Domain başarıyla kaydedildi";
$_LANG['domain_registration_error'] = "Domain kayıt hatası";
$_LANG['domain_renewed'] = "Domain başarıyla yenilendi";
$_LANG['domain_renewal_error'] = "Domain yenileme hatası";
$_LANG['transfer_cancelled'] = "Transfer iptal edildi";
$_LANG['auth_code'] = "Yetkilendirme Kodu";
$_LANG['transfer_domain'] = "Domain Transfer Et";
$_LANG['register_domain'] = "Domain Kaydet";
$_LANG['renew_domain'] = "Domain Yenile";
$_LANG['check_availability'] = "Müsaitlik Kontrolü";
$_LANG['type_required'] = "Tip gereklidir";
$_LANG['name_required'] = "İsim gereklidir";
$_LANG['content_required'] = "İçerik gereklidir";
$_LANG['priority_required'] = "Öncelik gereklidir";
$_LANG['invalid_priority'] = "Geçersiz öncelik değeri";
$_LANG['registration_years'] = "Kayıt Yılı";
$_LANG['year'] = "Yıl";
$_LANG['years'] = "Yıl";
$_LANG['auth_code_help'] = "Bu kodu mevcut kayıt şirketinizden alın";
$_LANG['zone_settings'] = "Zone Ayarları";
$_LANG['zone_info'] = "Zone Bilgileri";
$_LANG['check_ssl'] = "SSL Durumunu Kontrol Et";
$_LANG['back'] = "Geri";
$_LANG['availability_check_info'] = "Domain müsaitlik kontrolü Cloudflare API üzerinden doğrudan desteklenmemektedir. Lütfen domain müsaitliğini kayıt şirketinizden veya Cloudflare panelinden kontrol edin.";
$_LANG['domain_exists'] = "Domain Cloudflare'da zaten mevcut";
$_LANG['domain_may_be_available'] = "Domain müsait olabilir (kayıt şirketi ile kontrol edin)";
$_LANG['availability_check_not_supported'] = "Domain müsaitlik kontrolü API üzerinden doğrudan desteklenmemektedir";
$_LANG['zone_settings_desc'] = "Domainleriniz için Cloudflare ayarlarını yönetin";
$_LANG['select_domain'] = "Domain Seçin";
$_LANG['developer_mode'] = "Developer Mode";
$_LANG['enable_developer_mode'] = "Developer Mode'u Etkinleştir";
$_LANG['developer_mode_desc'] = "Cloudflare önbelleğini geçici olarak atla. 3 saat sonra otomatik olarak sona erer.";
$_LANG['security_level'] = "Güvenlik Seviyesi";
$_LANG['security_off'] = "Kapalı";
$_LANG['security_essentially_off'] = "Neredeyse Kapalı";
$_LANG['security_low'] = "Düşük";
$_LANG['security_medium'] = "Orta";
$_LANG['security_high'] = "Yüksek";
$_LANG['security_under_attack'] = "Saldırı Altındayım!";
$_LANG['security_level_desc'] = "Zone için güvenlik seviyesini ayarlayın. 'Saldırı Altındayım!' modu maksimum koruma sağlar.";
$_LANG['pause_cloudflare'] = "Cloudflare'ı Duraklat";
$_LANG['pause_cloudflare_desc'] = "Cloudflare'ı duraklat";
$_LANG['pause_cloudflare_help'] = "Duraklatıldığında Cloudflare trafiği proxy'lemeyi durdurur ancak DNS çalışmaya devam eder.";
$_LANG['remove_from_cloudflare'] = "Cloudflare'dan Kaldır";
$_LANG['remove_zone'] = "Zone'u Cloudflare'dan Kaldır";
$_LANG['remove_zone_warning'] = "UYARI: Bu işlem zone'u Cloudflare'dan kalıcı olarak silecektir. Bu işlem geri alınamaz!";
$_LANG['no_domains_found'] = "Domain bulunamadı. Lütfen önce domainleri senkronize edin.";
$_LANG['zone_settings_controls'] = "Zone Ayarları Kontrolleri";
$_LANG['ssl_tls_settings'] = "SSL/TLS Ayarları";
$_LANG['ssl_tls_mode'] = "SSL/TLS Modu";
$_LANG['ssl_off'] = "Off (not secure)";
$_LANG['ssl_flexible'] = "Flexible";
$_LANG['ssl_full'] = "Full";
$_LANG['ssl_strict'] = "Full (Strict)";
$_LANG['ssl_mode_desc'] = "Zone için SSL/TLS şifreleme modunu seçin.";
$_LANG['ssl_verification_status'] = "Doğrulama Durumu";