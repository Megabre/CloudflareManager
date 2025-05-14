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
