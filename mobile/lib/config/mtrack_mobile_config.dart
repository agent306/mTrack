class MTrackMobileConfig {
  const MTrackMobileConfig({
    required this.appTitle,
    required this.appContextLabel,
    required this.apiBaseUrl,
    required this.websocketUrl,
    required this.mobileDeviceName,
    required this.demoAccessToken,
    required this.demoRefreshToken,
    required this.demoRefreshedAccessToken,
    required this.demoTokenType,
    required this.demoSessionIssuedAt,
    required this.demoSessionExpiresInSeconds,
    required this.simulatedRealtimeEnabled,
    required this.simulatedRealtimeTicks,
    required this.simulatedRealtimeIntervalSeconds,
  });

  factory MTrackMobileConfig.fromEnvironment() {
    return MTrackMobileConfig(
      appTitle: const String.fromEnvironment(
        'MTRACK_APP_TITLE',
        defaultValue: 'mTrack',
      ),
      appContextLabel: const String.fromEnvironment(
        'MTRACK_APP_CONTEXT_LABEL',
        defaultValue: 'Customer Demo',
      ),
      apiBaseUrl: Uri.parse(
        const String.fromEnvironment(
          'MTRACK_API_BASE_URL',
          defaultValue: 'http://10.0.2.2:8000',
        ),
      ),
      websocketUrl: Uri.parse(
        const String.fromEnvironment(
          'MTRACK_WEBSOCKET_URL',
          defaultValue: 'ws://10.0.2.2:8080/app/local',
        ),
      ),
      mobileDeviceName: const String.fromEnvironment(
        'MTRACK_MOBILE_DEVICE_NAME',
        defaultValue: 'mTrack mobile',
      ),
      demoAccessToken: const String.fromEnvironment(
        'MTRACK_DEMO_ACCESS_TOKEN',
        defaultValue: 'demo-access-token',
      ),
      demoRefreshToken: const String.fromEnvironment(
        'MTRACK_DEMO_REFRESH_TOKEN',
        defaultValue: 'demo-refresh-token',
      ),
      demoRefreshedAccessToken: const String.fromEnvironment(
        'MTRACK_DEMO_REFRESHED_ACCESS_TOKEN',
        defaultValue: 'demo-access-token-refreshed',
      ),
      demoTokenType: const String.fromEnvironment(
        'MTRACK_DEMO_TOKEN_TYPE',
        defaultValue: 'Bearer',
      ),
      demoSessionIssuedAt:
          DateTime.tryParse(
            const String.fromEnvironment(
              'MTRACK_DEMO_SESSION_ISSUED_AT',
              defaultValue: '2026-06-08T08:00:00',
            ),
          ) ??
          DateTime(2026, 6, 8, 8),
      demoSessionExpiresInSeconds: int.fromEnvironment(
        'MTRACK_DEMO_SESSION_EXPIRES_IN_SECONDS',
        defaultValue: 3600,
      ),
      simulatedRealtimeEnabled: bool.fromEnvironment(
        'MTRACK_SIMULATED_REALTIME_ENABLED',
        defaultValue: true,
      ),
      simulatedRealtimeTicks: int.fromEnvironment(
        'MTRACK_SIMULATED_REALTIME_TICKS',
        defaultValue: 8,
      ),
      simulatedRealtimeIntervalSeconds: int.fromEnvironment(
        'MTRACK_SIMULATED_REALTIME_INTERVAL_SECONDS',
        defaultValue: 2,
      ),
    );
  }

  final String appTitle;
  final String appContextLabel;
  final Uri apiBaseUrl;
  final Uri websocketUrl;
  final String mobileDeviceName;
  final String demoAccessToken;
  final String demoRefreshToken;
  final String demoRefreshedAccessToken;
  final String demoTokenType;
  final DateTime demoSessionIssuedAt;
  final int demoSessionExpiresInSeconds;
  final bool simulatedRealtimeEnabled;
  final int simulatedRealtimeTicks;
  final int simulatedRealtimeIntervalSeconds;
}
