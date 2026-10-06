// Real APK (same-WiFi test): phone reaches PC via LAN IP.
// PC must stay on + XAMPP running. For anywhere-use, replace with HTTPS host.
class AppConfig {
  static const bool useMock = false;
  static const String apiBaseUrl = 'https://group5-boltron.free.nf/api';

  /// Public URL for a server-side profile filename (res_1_123.jpg).
  /// Local picked paths (containing / or \) return null — upload first.
  static String? photoUrl(String v) {
    if (v.isEmpty) return null;
    if (v.contains('/') || v.contains('\\') || v.contains(':')) return null;
    final base = apiBaseUrl.replaceAll(RegExp(r'/api$'), '');
    return '$base/uploads/profile_pics/$v';
  }
}
