import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mtrack/main.dart';

void main() {
  testWidgets('customer mobile shell exposes primary modules', (tester) async {
    await tester.pumpWidget(const MTrackApp());

    expect(find.text('Dashboard'), findsWidgets);
    expect(find.text('Fleet status'), findsOneWidget);
    expect(find.text('Speed graph'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.article_outlined).last);
    await tester.pumpAndSettle();

    expect(find.text('Report landing'), findsOneWidget);
    expect(find.text('Geofence'), findsWidgets);

    await tester.tap(find.byIcon(Icons.location_on_outlined).last);
    await tester.pumpAndSettle();

    expect(find.text('Google Maps'), findsOneWidget);
    expect(find.text('Search 3 fleets'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.play_circle_outline).last);
    await tester.pumpAndSettle();

    expect(find.text('Playback controls'), findsOneWidget);
    expect(find.text('Trip summary'), findsOneWidget);
  });
}
