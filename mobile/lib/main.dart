import 'package:flutter/material.dart';

import 'theme/mtrack_theme.dart';

void main() {
  runApp(const MTrackApp());
}

class MTrackApp extends StatelessWidget {
  const MTrackApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'mTrack',
      theme: MTrackTheme.light(),
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
        titleSpacing: MTrackTokens.space16,
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
          padding: const EdgeInsets.all(MTrackTokens.space16),
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
          padding: const EdgeInsets.all(MTrackTokens.space24),
          decoration: BoxDecoration(
            color: MTrackTokens.primaryDark,
            borderRadius: BorderRadius.circular(MTrackTokens.radiusLarge),
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
              const SizedBox(height: MTrackTokens.space8),
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
        const SizedBox(height: MTrackTokens.space16),
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
      padding: const EdgeInsets.all(MTrackTokens.space16),
      decoration: MTrackTheme.panelDecoration(),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(MTrackTokens.radiusMedium),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: MTrackTokens.space12),
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
