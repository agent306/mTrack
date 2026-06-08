import 'package:flutter/material.dart';

void main() {
  runApp(const MTrackApp());
}

class MTrackTokens {
  static const brand = Color(0xFF14B8A6);
  static const brandHover = Color(0xFF0F9F8F);
  static const primaryDark = Color(0xFF0B1026);
  static const page = Color(0xFFF4F6FA);
  static const surface = Color(0xFFFFFFFF);
  static const mutedSurface = Color(0xFFEEF2F7);
  static const border = Color(0xFFD8DEE8);
  static const text = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const success = Color(0xFF22C55E);
  static const warning = Color(0xFFF97316);
}

class MTrackApp extends StatelessWidget {
  const MTrackApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'mTrack',
      theme: ThemeData(
        useMaterial3: true,
        scaffoldBackgroundColor: MTrackTokens.page,
        colorScheme: ColorScheme.fromSeed(
          seedColor: MTrackTokens.brand,
          brightness: Brightness.light,
          surface: MTrackTokens.surface,
        ),
        fontFamily: 'Roboto',
        textTheme: const TextTheme(
          headlineSmall: TextStyle(
            color: MTrackTokens.text,
            fontSize: 24,
            fontWeight: FontWeight.w700,
            height: 32 / 24,
          ),
          titleMedium: TextStyle(
            color: MTrackTokens.text,
            fontSize: 18,
            fontWeight: FontWeight.w700,
            height: 28 / 18,
          ),
          bodyMedium: TextStyle(
            color: MTrackTokens.text,
            fontSize: 14,
            fontWeight: FontWeight.w400,
            height: 22 / 14,
          ),
          labelSmall: TextStyle(
            color: MTrackTokens.muted,
            fontSize: 12,
            fontWeight: FontWeight.w500,
            height: 18 / 12,
          ),
        ),
      ),
      home: const MobileShell(),
    );
  }
}

class MobileShell extends StatefulWidget {
  const MobileShell({super.key});

  @override
  State<MobileShell> createState() => _MobileShellState();
}

class _MobileShellState extends State<MobileShell> {
  int _selectedIndex = 0;

  static const _destinations = <Destination>[
    Destination('Dashboard', Icons.dashboard_outlined),
    Destination('Reports', Icons.article_outlined),
    Destination('Live', Icons.location_on_outlined),
    Destination('Playback', Icons.play_circle_outline),
    Destination('Setting', Icons.settings_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    final selected = _destinations[_selectedIndex];

    return Scaffold(
      appBar: AppBar(
        backgroundColor: MTrackTokens.surface,
        elevation: 0,
        surfaceTintColor: MTrackTokens.surface,
        titleSpacing: 16,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'mTrack',
              style: TextStyle(fontSize: 12, color: MTrackTokens.muted),
            ),
            Text(
              selected.label,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Search',
            onPressed: () {},
            icon: const Icon(Icons.search, color: MTrackTokens.text),
          ),
          IconButton(
            tooltip: 'Filters',
            onPressed: () {},
            icon: const Icon(Icons.tune, color: MTrackTokens.text),
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: FoundationPanel(destination: selected),
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        backgroundColor: MTrackTokens.surface,
        indicatorColor: MTrackTokens.brand.withValues(alpha: 0.16),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        onDestinationSelected: (index) {
          setState(() {
            _selectedIndex = index;
          });
        },
        destinations: [
          for (final item in _destinations)
            NavigationDestination(
              icon: Icon(item.icon),
              selectedIcon: Icon(item.icon, color: MTrackTokens.brandHover),
              label: item.label,
            ),
        ],
      ),
    );
  }
}

class FoundationPanel extends StatelessWidget {
  const FoundationPanel({super.key, required this.destination});

  final Destination destination;

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: MTrackTokens.primaryDark,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(destination.icon, color: MTrackTokens.brand, size: 28),
              const SizedBox(height: 18),
              Text(
                '${destination.label} foundation',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w700,
                  height: 32 / 24,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Mobile navigation, theme tokens, and placeholder states are ready for feature phases.',
                style: TextStyle(
                  color: Color(0xB3FFFFFF),
                  fontSize: 14,
                  height: 22 / 14,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const _StatusTile(
          label: 'Authentication',
          value: 'Mobile token scaffold',
          icon: Icons.verified_user_outlined,
          color: MTrackTokens.success,
        ),
        const _StatusTile(
          label: 'Realtime',
          value: 'WebSocket-ready architecture',
          icon: Icons.wifi_tethering,
          color: MTrackTokens.brand,
        ),
        const _StatusTile(
          label: 'Maps',
          value: 'Platform map packages deferred to tracking phase',
          icon: Icons.map_outlined,
          color: MTrackTokens.warning,
        ),
      ],
    );
  }
}

class _StatusTile extends StatelessWidget {
  const _StatusTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: MTrackTokens.surface,
        border: Border.all(color: MTrackTokens.border),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: Theme.of(context).textTheme.labelSmall),
                const SizedBox(height: 2),
                Text(value, style: Theme.of(context).textTheme.bodyMedium),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class Destination {
  const Destination(this.label, this.icon);

  final String label;
  final IconData icon;
}
