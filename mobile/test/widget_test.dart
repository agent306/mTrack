import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mtrack/main.dart';

void main() {
  testWidgets('mobile foundation shell switches primary tabs', (tester) async {
    await tester.pumpWidget(const MTrackApp());

    expect(find.text('Dashboard foundation'), findsOneWidget);
    expect(find.text('Reports foundation'), findsNothing);

    await tester.tap(find.byIcon(Icons.article_outlined).last);
    await tester.pumpAndSettle();

    expect(find.text('Reports foundation'), findsOneWidget);
  });
}
