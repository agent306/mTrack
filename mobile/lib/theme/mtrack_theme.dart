import 'package:flutter/material.dart';

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
  static const danger = Color(0xFFEF4444);
  static const info = Color(0xFF67E8F9);
  static const neutral = Color(0xFF6B7280);

  static const space4 = 4.0;
  static const space8 = 8.0;
  static const space12 = 12.0;
  static const space16 = 16.0;
  static const space24 = 24.0;
  static const space32 = 32.0;
  static const space48 = 48.0;

  static const radiusSmall = 6.0;
  static const radiusMedium = 10.0;
  static const radiusLarge = 16.0;
  static const radiusSheet = 24.0;
}

class MTrackTheme {
  static ThemeData light() {
    return ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: MTrackTokens.page,
      colorScheme: ColorScheme.fromSeed(
        seedColor: MTrackTokens.brand,
        brightness: Brightness.light,
        primary: MTrackTokens.brand,
        surface: MTrackTokens.surface,
        error: MTrackTokens.danger,
      ),
      fontFamily: 'Roboto',
      textTheme: const TextTheme(
        displaySmall: TextStyle(
          color: MTrackTokens.text,
          fontSize: 32,
          fontWeight: FontWeight.w700,
          height: 40 / 32,
        ),
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
      appBarTheme: const AppBarTheme(
        backgroundColor: MTrackTokens.surface,
        elevation: 0,
        surfaceTintColor: MTrackTokens.surface,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: MTrackTokens.surface,
        indicatorColor: MTrackTokens.brand.withValues(alpha: 0.16),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            color:
                states.contains(WidgetState.selected)
                    ? MTrackTokens.brandHover
                    : MTrackTokens.muted,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: MTrackTokens.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 12,
          vertical: 10,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(MTrackTokens.radiusSmall),
          borderSide: const BorderSide(color: MTrackTokens.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(MTrackTokens.radiusSmall),
          borderSide: const BorderSide(color: MTrackTokens.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(MTrackTokens.radiusSmall),
          borderSide: const BorderSide(color: MTrackTokens.brand, width: 2),
        ),
      ),
    );
  }

  static BoxDecoration panelDecoration({
    double radius = MTrackTokens.radiusMedium,
  }) {
    return BoxDecoration(
      color: MTrackTokens.surface,
      border: Border.all(color: MTrackTokens.border),
      borderRadius: BorderRadius.circular(radius),
      boxShadow: const [
        BoxShadow(
          color: Color(0x141F2937),
          blurRadius: 2,
          offset: Offset(0, 1),
        ),
      ],
    );
  }

  static Color statusColor(String status) {
    return switch (status) {
      'online' || 'active' || 'moving' || 'entrance' => MTrackTokens.success,
      'idle' || 'overspeed' || 'warning' => MTrackTokens.warning,
      'offline' ||
      'expired' ||
      'rejected' ||
      'exit' ||
      'open' => MTrackTokens.danger,
      'resolved' => MTrackTokens.success,
      _ => MTrackTokens.neutral,
    };
  }
}
