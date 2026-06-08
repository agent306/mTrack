import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'config/mtrack_mobile_config.dart';
import 'data/mtrack_mobile_repository.dart';
import 'theme/mtrack_theme.dart';

void main() {
  final config = MTrackMobileConfig.fromEnvironment();

  runApp(MTrackApp(config: config));
}

class MTrackApp extends StatelessWidget {
  const MTrackApp({super.key, this.config});

  final MTrackMobileConfig? config;

  @override
  Widget build(BuildContext context) {
    final resolvedConfig = config ?? MTrackMobileConfig.fromEnvironment();

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: resolvedConfig.appTitle,
      theme: MTrackTheme.light(),
      home: MobileShell(
        repository: MTrackMobileRepository(config: resolvedConfig),
      ),
    );
  }
}

class MobileShell extends StatefulWidget {
  const MobileShell({super.key, required this.repository});

  final MTrackMobileRepository repository;

  @override
  State<MobileShell> createState() => _MobileShellState();
}

class _MobileShellState extends State<MobileShell> {
  int _selectedIndex = 0;
  late MobileAuthSession _session;
  late List<TrackerDevice> _trackers;
  StreamSubscription<TrackerLocation>? _realtimeSubscription;

  static const _destinations = <Destination>[
    Destination('Dashboard', Icons.dashboard_outlined),
    Destination('Reports', Icons.article_outlined),
    Destination('Live', Icons.location_on_outlined),
    Destination('Playback', Icons.play_circle_outline),
    Destination('Setting', Icons.settings_outlined),
  ];

  @override
  void initState() {
    super.initState();
    _session = widget.repository.demoSession;
    _trackers = widget.repository.trackers();
    _subscribeToRealtime();
  }

  @override
  void dispose() {
    _realtimeSubscription?.cancel();
    super.dispose();
  }

  void _subscribeToRealtime() {
    _realtimeSubscription = widget.repository.simulatedRealtime().listen((
      location,
    ) {
      setState(() {
        _trackers = [
          for (final tracker in _trackers)
            if (tracker.id == location.trackerId)
              TrackerDevice(
                id: tracker.id,
                name: tracker.name,
                group: tracker.group,
                status: location.speed > 1 ? 'moving' : 'idle',
                latitude: location.latitude,
                longitude: location.longitude,
                speed: location.speed,
                lastSeen: location.occurredAt,
                batteryPercent: tracker.batteryPercent,
              )
            else
              tracker,
        ];
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final selected = _destinations[_selectedIndex];

    return Scaffold(
      appBar: AppBar(
        titleSpacing: MTrackTokens.space16,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.repository.config.appContextLabel,
              style: const TextStyle(fontSize: 12, color: MTrackTokens.muted),
            ),
            Text(
              selected.label,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ],
        ),
        actions: [
          if (_selectedIndex == 2)
            IconButton(
              tooltip: 'Add fleet',
              onPressed: () {},
              icon: const Icon(
                Icons.add_circle_outline,
                color: MTrackTokens.text,
              ),
            ),
          IconButton(
            tooltip: 'Search',
            onPressed: () => _showSearchSheet(context),
            icon: const Icon(Icons.search, color: MTrackTokens.text),
          ),
          IconButton(
            tooltip: 'Filters',
            onPressed: () => _showFilterSheet(context),
            icon: const Icon(Icons.tune, color: MTrackTokens.text),
          ),
        ],
      ),
      body: SafeArea(child: _contentForIndex(_selectedIndex)),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
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

  Widget _contentForIndex(int index) {
    return switch (index) {
      0 => DashboardScreen(
        trackers: _trackers,
        alerts: widget.repository.alerts(),
        analytics: widget.repository.analytics(),
      ),
      1 => ReportsScreen(
        reports: widget.repository.reports(),
        alerts: widget.repository.alerts(),
        routePoints: widget.repository.routePoints(),
      ),
      2 => LiveScreen(trackers: _trackers),
      3 => PlaybackScreen(
        routePoints: widget.repository.routePoints(),
        analytics: widget.repository.analytics(),
      ),
      _ => SettingScreen(
        session: _session,
        reports: widget.repository.reports(),
        onRefreshSession: () {
          setState(() {
            _session = widget.repository.refreshedDemoSession(_session);
          });
        },
      ),
    };
  }

  void _showSearchSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder:
          (context) => Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Fleet search',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 12),
                TextField(
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.search),
                    hintText: 'Search tracker, group, identifier',
                  ),
                  onChanged: (_) {},
                ),
                const SizedBox(height: 12),
                for (final tracker in _trackers.take(3))
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: StatusDot(status: tracker.status),
                    title: Text(tracker.name),
                    subtitle: Text(
                      '${tracker.group} · ${tracker.speed.toStringAsFixed(0)} km/h',
                    ),
                  ),
              ],
            ),
          ),
    );
  }

  void _showFilterSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder:
          (context) => Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Filters', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                const Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    FilterChip(
                      selected: true,
                      label: Text('Moving'),
                      onSelected: null,
                    ),
                    FilterChip(
                      selected: true,
                      label: Text('Idle'),
                      onSelected: null,
                    ),
                    FilterChip(
                      selected: false,
                      label: Text('Offline'),
                      onSelected: null,
                    ),
                    FilterChip(
                      selected: true,
                      label: Text('Delivery'),
                      onSelected: null,
                    ),
                  ],
                ),
              ],
            ),
          ),
    );
  }
}

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    super.key,
    required this.trackers,
    required this.alerts,
    required this.analytics,
  });

  final List<TrackerDevice> trackers;
  final List<AlertEvent> alerts;
  final TripAnalytics analytics;

  @override
  Widget build(BuildContext context) {
    final moving =
        trackers.where((tracker) => tracker.status == 'moving').length;
    final offline =
        trackers.where((tracker) => tracker.status == 'offline').length;

    return ListView(
      padding: const EdgeInsets.all(MTrackTokens.space16),
      children: [
        Row(
          children: [
            Expanded(
              child: MetricCard(
                label: 'Fleet status',
                value: '$moving moving',
                helper: '${trackers.length} visible trackers',
                icon: Icons.wifi_tethering,
                tone: MTrackTokens.brand,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: MetricCard(
                label: 'Offline',
                value: offline.toString(),
                helper: 'Needs attention',
                icon: Icons.wifi_off,
                tone: MTrackTokens.danger,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: MetricCard(
                label: 'Moving hours',
                value: '${(analytics.movingMinutes / 60).toStringAsFixed(1)} h',
                helper: 'Recent route',
                icon: Icons.schedule,
                tone: MTrackTokens.info,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: MetricCard(
                label: 'Max speed',
                value: analytics.maxSpeed.toStringAsFixed(0),
                helper: 'km/h',
                icon: Icons.speed,
                tone: MTrackTokens.warning,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'Speed graph',
          child: SizedBox(
            height: 160,
            child: SpeedGraph(points: analytics.segments),
          ),
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'Events',
          child: Column(
            children: [
              for (final alert in alerts)
                EventRow(
                  title: alert.type,
                  subtitle:
                      '${alert.trackerName} · ${formatTime(alert.occurredAt)}',
                  status: alert.status,
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class ReportsScreen extends StatelessWidget {
  const ReportsScreen({
    super.key,
    required this.reports,
    required this.alerts,
    required this.routePoints,
  });

  final List<ReportItem> reports;
  final List<AlertEvent> alerts;
  final List<RoutePoint> routePoints;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(MTrackTokens.space16),
      children: [
        SectionPanel(
          title: 'Report landing',
          child: Column(
            children: [
              for (final report in reports) ReportTile(report: report),
            ],
          ),
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'CSV exports',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: const [
                  InputChip(label: Text('tracker')),
                  InputChip(label: Text('event_timestamp')),
                  InputChip(label: Text('speed')),
                  InputChip(label: Text('status')),
                  InputChip(label: Text('reason')),
                ],
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () {},
                icon: const Icon(Icons.download_outlined),
                label: const Text('Export selected report'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class LiveScreen extends StatefulWidget {
  const LiveScreen({super.key, required this.trackers});

  final List<TrackerDevice> trackers;

  @override
  State<LiveScreen> createState() => _LiveScreenState();
}

class _LiveScreenState extends State<LiveScreen> {
  TrackerDevice? _selectedTracker;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned.fill(
          child: AdaptiveMapSurface(
            trackers: widget.trackers,
            routePoints: const [],
            selectedTracker: _selectedTracker,
            onTrackerSelected: (tracker) {
              setState(() {
                _selectedTracker = tracker;
              });
              _showTrackerSheet(context, tracker);
            },
          ),
        ),
        Positioned(
          left: 16,
          right: 16,
          top: 16,
          child: SearchOverlay(trackers: widget.trackers),
        ),
        Positioned(
          left: 16,
          right: 16,
          bottom: 16,
          child: SizedBox(
            height: 116,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemBuilder:
                  (context, index) => TrackerCard(
                    tracker: widget.trackers[index],
                    onTap: () {
                      setState(() {
                        _selectedTracker = widget.trackers[index];
                      });
                      _showTrackerSheet(context, widget.trackers[index]);
                    },
                  ),
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemCount: widget.trackers.length,
            ),
          ),
        ),
      ],
    );
  }

  void _showTrackerSheet(BuildContext context, TrackerDevice tracker) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => TrackerDetailSheet(tracker: tracker),
    );
  }
}

class PlaybackScreen extends StatefulWidget {
  const PlaybackScreen({
    super.key,
    required this.routePoints,
    required this.analytics,
  });

  final List<RoutePoint> routePoints;
  final TripAnalytics analytics;

  @override
  State<PlaybackScreen> createState() => _PlaybackScreenState();
}

class _PlaybackScreenState extends State<PlaybackScreen> {
  int _index = 0;
  double _speed = 1;
  bool _playing = false;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final current =
        widget.routePoints[_index.clamp(0, widget.routePoints.length - 1)];

    return ListView(
      padding: const EdgeInsets.all(MTrackTokens.space16),
      children: [
        SectionPanel(
          title: 'Fleet and date',
          child: Column(
            children: [
              TextField(
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.search),
                  hintText: current.trackerName,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {},
                      icon: const Icon(Icons.calendar_today_outlined),
                      label: const Text('Jun 8, 2026'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: DropdownButtonFormField<double>(
                      value: _speed,
                      items: const [
                        DropdownMenuItem(value: 1, child: Text('1x')),
                        DropdownMenuItem(value: 2, child: Text('2x')),
                        DropdownMenuItem(value: 4, child: Text('4x')),
                      ],
                      onChanged: (value) => setState(() => _speed = value ?? 1),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        SizedBox(
          height: 360,
          child: AdaptiveMapSurface(
            trackers: const [],
            routePoints: widget.routePoints,
            playbackPoint: current,
          ),
        ),
        const SizedBox(height: 12),
        SectionPanel(
          title: 'Playback controls',
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  IconButton.filled(
                    onPressed: _togglePlayback,
                    icon: Icon(_playing ? Icons.pause : Icons.play_arrow),
                  ),
                  IconButton(
                    onPressed:
                        () => setState(
                          () =>
                              _index = (_index - 1).clamp(
                                0,
                                widget.routePoints.length - 1,
                              ),
                        ),
                    icon: const Icon(Icons.skip_previous),
                  ),
                  Text('${_index + 1}/${widget.routePoints.length}'),
                  IconButton(
                    onPressed:
                        () => setState(
                          () =>
                              _index = (_index + 1).clamp(
                                0,
                                widget.routePoints.length - 1,
                              ),
                        ),
                    icon: const Icon(Icons.skip_next),
                  ),
                ],
              ),
              Slider(
                value: _index.toDouble(),
                min: 0,
                max: (widget.routePoints.length - 1).toDouble(),
                divisions: widget.routePoints.length - 1,
                onChanged: (value) => setState(() => _index = value.round()),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'Trip summary',
          child: Column(
            children: [
              SummaryGrid(analytics: widget.analytics),
              const SizedBox(height: 12),
              for (final stop in widget.analytics.stops)
                EventRow(
                  title: 'Stop',
                  subtitle:
                      '${stop.trackerName} · ${formatTime(stop.timestamp)}',
                  status: 'idle',
                ),
            ],
          ),
        ),
      ],
    );
  }

  void _togglePlayback() {
    if (_playing) {
      _timer?.cancel();
      setState(() => _playing = false);
      return;
    }

    setState(() => _playing = true);
    _timer = Timer.periodic(Duration(milliseconds: (900 / _speed).round()), (
      _,
    ) {
      setState(() {
        _index = _index >= widget.routePoints.length - 1 ? 0 : _index + 1;
      });
    });
  }
}

class SettingScreen extends StatelessWidget {
  const SettingScreen({
    super.key,
    required this.session,
    required this.reports,
    required this.onRefreshSession,
  });

  final MobileAuthSession session;
  final List<ReportItem> reports;
  final VoidCallback onRefreshSession;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(MTrackTokens.space16),
      children: [
        SectionPanel(
          title: 'Mobile JWT auth',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              StatusPill(
                label: session.expiresSoon ? 'refresh soon' : 'active',
              ),
              const SizedBox(height: 8),
              Text(
                'Token type ${session.tokenType}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              Text(
                'Issued ${formatTime(session.issuedAt)}',
                style: Theme.of(context).textTheme.labelSmall,
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: onRefreshSession,
                icon: const Icon(Icons.refresh),
                label: const Text('Refresh access token'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'Dashboard preferences',
          child: Column(
            children: const [
              SwitchListTile(
                value: true,
                onChanged: null,
                title: Text('Compact dashboard'),
                subtitle: Text('Dense cards for daily monitoring'),
              ),
              SwitchListTile(
                value: true,
                onChanged: null,
                title: Text('Geofence alerts'),
                subtitle: Text('Entrance, exit, and max-speed alerts'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'Users and roles',
          child: Column(
            children: const [
              EventRow(
                title: 'Customer Admin',
                subtitle: 'All modules · edit',
                status: 'active',
              ),
              EventRow(
                title: 'Dispatcher',
                subtitle: 'Live and playback · view',
                status: 'active',
              ),
              EventRow(
                title: 'Auditor',
                subtitle: 'Reports and audit · view',
                status: 'active',
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        SectionPanel(
          title: 'Device logs',
          child: Column(
            children: [
              for (final report in reports
                  .where((report) => report.title.contains('Logs'))
                  .take(2))
                ReportTile(report: report),
            ],
          ),
        ),
      ],
    );
  }
}

class AdaptiveMapSurface extends StatelessWidget {
  const AdaptiveMapSurface({
    super.key,
    required this.trackers,
    required this.routePoints,
    this.selectedTracker,
    this.playbackPoint,
    this.onTrackerSelected,
  });

  final List<TrackerDevice> trackers;
  final List<RoutePoint> routePoints;
  final TrackerDevice? selectedTracker;
  final RoutePoint? playbackPoint;
  final ValueChanged<TrackerDevice>? onTrackerSelected;

  @override
  Widget build(BuildContext context) {
    final provider = switch (defaultTargetPlatform) {
      TargetPlatform.android => 'Google Maps',
      TargetPlatform.iOS => 'Apple Maps',
      _ => 'Mobile map',
    };
    final markerSource = [
      ...trackers.map(
        (tracker) => MapPoint(tracker.latitude, tracker.longitude),
      ),
      ...routePoints.map((point) => MapPoint(point.latitude, point.longitude)),
    ];

    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFE8EEF3),
        border: Border.all(color: MTrackTokens.border),
        borderRadius: BorderRadius.circular(MTrackTokens.radiusMedium),
      ),
      clipBehavior: Clip.antiAlias,
      child: LayoutBuilder(
        builder: (context, constraints) {
          return Stack(
            children: [
              MapGrid(provider: provider),
              for (final point in routePoints)
                Positioned(
                  left: mapX(
                    point.longitude,
                    markerSource,
                    constraints.maxWidth,
                  ),
                  top: mapY(
                    point.latitude,
                    markerSource,
                    constraints.maxHeight,
                  ),
                  child: const RouteDot(),
                ),
              for (final tracker in trackers)
                Positioned(
                  left: mapX(
                    tracker.longitude,
                    markerSource,
                    constraints.maxWidth,
                  ),
                  top: mapY(
                    tracker.latitude,
                    markerSource,
                    constraints.maxHeight,
                  ),
                  child: GestureDetector(
                    onTap: () => onTrackerSelected?.call(tracker),
                    child: MapMarker(
                      label: tracker.name.characters.first,
                      status: tracker.status,
                      selected: selectedTracker?.id == tracker.id,
                    ),
                  ),
                ),
              if (playbackPoint != null)
                Positioned(
                  left: mapX(
                    playbackPoint!.longitude,
                    markerSource,
                    constraints.maxWidth,
                  ),
                  top: mapY(
                    playbackPoint!.latitude,
                    markerSource,
                    constraints.maxHeight,
                  ),
                  child: MapMarker(
                    label: playbackPoint!.trackerName.characters.first,
                    status: 'moving',
                    selected: true,
                  ),
                ),
              Positioned(
                right: 12,
                bottom: 12,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.92),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    child: Text(
                      provider,
                      style: Theme.of(context).textTheme.labelSmall,
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class MapGrid extends StatelessWidget {
  const MapGrid({super.key, required this.provider});

  final String provider;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _MapGridPainter(),
      child: const SizedBox.expand(),
    );
  }
}

class _MapGridPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final line =
        Paint()
          ..color = MTrackTokens.border
          ..strokeWidth = 1;
    final wash = Paint()..color = MTrackTokens.brand.withValues(alpha: 0.08);

    canvas.drawCircle(Offset(size.width * 0.22, size.height * 0.72), 92, wash);
    canvas.drawCircle(Offset(size.width * 0.78, size.height * 0.28), 106, wash);

    for (var x = 0.0; x < size.width; x += 64) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), line);
    }

    for (var y = 0.0; y < size.height; y += 64) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), line);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class SearchOverlay extends StatelessWidget {
  const SearchOverlay({super.key, required this.trackers});

  final List<TrackerDevice> trackers;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: MTrackTheme.panelDecoration(radius: MTrackTokens.radiusLarge),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Row(
          children: [
            const Icon(Icons.search, color: MTrackTokens.muted),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Search ${trackers.length} fleets',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ),
            const Icon(Icons.tune, color: MTrackTokens.text),
          ],
        ),
      ),
    );
  }
}

class TrackerCard extends StatelessWidget {
  const TrackerCard({super.key, required this.tracker, required this.onTap});

  final TrackerDevice tracker;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 260,
      child: GestureDetector(
        onTap: onTap,
        child: DecoratedBox(
          decoration: MTrackTheme.panelDecoration(
            radius: MTrackTokens.radiusLarge,
          ),
          child: Padding(
            padding: const EdgeInsets.all(MTrackTokens.space16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    StatusDot(status: tracker.status),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        tracker.name,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    ),
                    StatusPill(label: tracker.status),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  tracker.group,
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                const SizedBox(height: 4),
                Text(
                  '${tracker.speed.toStringAsFixed(0)} km/h · ${formatTime(tracker.lastSeen)}',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class TrackerDetailSheet extends StatelessWidget {
  const TrackerDetailSheet({super.key, required this.tracker});

  final TrackerDevice tracker;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              StatusDot(status: tracker.status),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  tracker.name,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              StatusPill(label: tracker.status),
            ],
          ),
          const SizedBox(height: 12),
          SummaryRow(label: 'Group', value: tracker.group),
          SummaryRow(
            label: 'Speed',
            value: '${tracker.speed.toStringAsFixed(0)} km/h',
          ),
          SummaryRow(label: 'Battery', value: '${tracker.batteryPercent}%'),
          SummaryRow(label: 'Last seen', value: formatTime(tracker.lastSeen)),
          SummaryRow(
            label: 'Coordinates',
            value:
                '${tracker.latitude.toStringAsFixed(4)}, ${tracker.longitude.toStringAsFixed(4)}',
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.play_circle_outline),
            label: const Text('Open playback'),
          ),
        ],
      ),
    );
  }
}

class MetricCard extends StatelessWidget {
  const MetricCard({
    super.key,
    required this.label,
    required this.value,
    required this.helper,
    required this.icon,
    required this.tone,
  });

  final String label;
  final String value;
  final String helper;
  final IconData icon;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: MTrackTheme.panelDecoration(),
      child: Padding(
        padding: const EdgeInsets.all(MTrackTokens.space16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: tone),
            const SizedBox(height: 16),
            Text(label, style: Theme.of(context).textTheme.labelSmall),
            const SizedBox(height: 4),
            Text(
              value,
              style: const TextStyle(
                color: MTrackTokens.text,
                fontSize: 24,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(helper, style: Theme.of(context).textTheme.labelSmall),
          ],
        ),
      ),
    );
  }
}

class SectionPanel extends StatelessWidget {
  const SectionPanel({super.key, required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: MTrackTheme.panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(MTrackTokens.space16),
            child: Text(title, style: Theme.of(context).textTheme.titleMedium),
          ),
          const Divider(height: 1, color: MTrackTokens.border),
          Padding(
            padding: const EdgeInsets.all(MTrackTokens.space16),
            child: child,
          ),
        ],
      ),
    );
  }
}

class SpeedGraph extends StatelessWidget {
  const SpeedGraph({super.key, required this.points});

  final List<TripSegment> points;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        for (final segment in points)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 3),
              child: Container(
                height: segment.type == 'moving' ? 132 : 22,
                decoration: BoxDecoration(
                  color:
                      segment.type == 'moving'
                          ? MTrackTokens.brand
                          : MTrackTokens.warning,
                  borderRadius: BorderRadius.circular(MTrackTokens.radiusSmall),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class SummaryGrid extends StatelessWidget {
  const SummaryGrid({super.key, required this.analytics});

  final TripAnalytics analytics;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 2.3,
      children: [
        SummaryTile(
          label: 'Distance',
          value: '${analytics.distanceKm.toStringAsFixed(1)} km',
        ),
        SummaryTile(label: 'Moving', value: '${analytics.movingMinutes}m'),
        SummaryTile(label: 'Idle', value: '${analytics.idleMinutes}m'),
        SummaryTile(
          label: 'Max speed',
          value: analytics.maxSpeed.toStringAsFixed(0),
        ),
      ],
    );
  }
}

class SummaryTile extends StatelessWidget {
  const SummaryTile({super.key, required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        border: Border(
          right: BorderSide(color: MTrackTokens.border),
          bottom: BorderSide(color: MTrackTokens.border),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.labelSmall),
            Text(
              value,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

class ReportTile extends StatelessWidget {
  const ReportTile({super.key, required this.report});

  final ReportItem report;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleIcon(icon: iconForReport(report.iconName)),
      title: Text(report.title),
      subtitle: Text(report.description),
      trailing: StatusPill(label: report.count.toString()),
    );
  }
}

class EventRow extends StatelessWidget {
  const EventRow({
    super.key,
    required this.title,
    required this.subtitle,
    required this.status,
  });

  final String title;
  final String subtitle;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          StatusDot(status: status),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Text(subtitle, style: Theme.of(context).textTheme.labelSmall),
              ],
            ),
          ),
          StatusPill(label: status),
        ],
      ),
    );
  }
}

class SummaryRow extends StatelessWidget {
  const SummaryRow({super.key, required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: Theme.of(context).textTheme.labelSmall),
          ),
          Text(value, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    );
  }
}

class CircleIcon extends StatelessWidget {
  const CircleIcon({super.key, required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: MTrackTokens.brand.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(MTrackTokens.radiusMedium),
      ),
      child: Icon(icon, color: MTrackTokens.brand, size: 22),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final color = MTrackTheme.statusColor(label);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Text(
          label,
          style: TextStyle(
            color: color,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class StatusDot extends StatelessWidget {
  const StatusDot({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 10,
      height: 10,
      decoration: BoxDecoration(
        color: MTrackTheme.statusColor(status),
        shape: BoxShape.circle,
      ),
    );
  }
}

class MapMarker extends StatelessWidget {
  const MapMarker({
    super.key,
    required this.label,
    required this.status,
    required this.selected,
  });

  final String label;
  final String status;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Transform.translate(
      offset: const Offset(-18, -18),
      child: Container(
        width: selected ? 42 : 34,
        height: selected ? 42 : 34,
        decoration: BoxDecoration(
          color:
              selected ? MTrackTokens.brand : MTrackTheme.statusColor(status),
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 3),
          boxShadow: const [
            BoxShadow(
              color: Color(0x29111827),
              blurRadius: 14,
              offset: Offset(0, 8),
            ),
          ],
        ),
        child: Center(
          child: Text(
            label,
            style: const TextStyle(
              color: MTrackTokens.primaryDark,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
  }
}

class RouteDot extends StatelessWidget {
  const RouteDot({super.key});

  @override
  Widget build(BuildContext context) {
    return Transform.translate(
      offset: const Offset(-4, -4),
      child: const DecoratedBox(
        decoration: BoxDecoration(
          color: Color(0xCC111827),
          shape: BoxShape.circle,
        ),
        child: SizedBox(width: 8, height: 8),
      ),
    );
  }
}

class MapPoint {
  const MapPoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;
}

class Destination {
  const Destination(this.label, this.icon);

  final String label;
  final IconData icon;
}

double mapX(double longitude, List<MapPoint> source, double width) {
  final values = source.map((point) => point.longitude).toList();
  final min = values.reduce((a, b) => a < b ? a : b);
  final max = values.reduce((a, b) => a > b ? a : b);
  final range = (max - min).abs() < 0.01 ? 0.01 : max - min;

  return 24 + ((longitude - min) / range) * (width - 48);
}

double mapY(double latitude, List<MapPoint> source, double height) {
  final values = source.map((point) => point.latitude).toList();
  final min = values.reduce((a, b) => a < b ? a : b);
  final max = values.reduce((a, b) => a > b ? a : b);
  final range = (max - min).abs() < 0.01 ? 0.01 : max - min;

  return height - (24 + ((latitude - min) / range) * (height - 48));
}

String formatTime(DateTime value) {
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');

  return '$hour:$minute';
}

IconData iconForReport(String iconName) {
  return switch (iconName) {
    'map' => Icons.map_outlined,
    'speed' => Icons.speed_outlined,
    'devices' => Icons.devices_other_outlined,
    'route' => Icons.route_outlined,
    'logs' => Icons.list_alt_outlined,
    'payload' => Icons.integration_instructions_outlined,
    'audit' => Icons.fact_check_outlined,
    _ => Icons.analytics_outlined,
  };
}
