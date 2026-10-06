import 'package:flutter/material.dart';
import '../theme.dart';

/// Dark 60px header bar like AIA HorizontalArrangement1.
class AppHeader extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final bool showBack;
  const AppHeader({super.key, required this.title, this.showBack = false});

  @override
  Size get preferredSize => const Size.fromHeight(60);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: AppTheme.headerBg,
      automaticallyImplyLeading: showBack,
      title: Row(
        children: [
          Image.asset('assets/logo_bhc.png', width: 36, height: 36,
              errorBuilder: (_, _, _) => const Icon(Icons.local_hospital, color: Colors.white)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(title,
                style: const TextStyle(fontSize: 19, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }
}

/// White rounded card mimicking card_white.png container.
class WhiteCard extends StatelessWidget {
  final Widget child;
  const WhiteCard({super.key, required this.child});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 8, offset: Offset(0, 3))],
      ),
      child: child,
    );
  }
}

/// Navy background with bg_navy.jpg overlay (falls back to plain navy).
class NavyScaffold extends StatelessWidget {
  final PreferredSizeWidget? appBar;
  final Widget body;
  const NavyScaffold({super.key, this.appBar, required this.body});
  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppTheme.navy,
      child: Stack(
        children: [
          Positioned.fill(
            child: Image.asset('assets/bg_navy.jpg', fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const SizedBox()),
          ),
          Scaffold(
            backgroundColor: Colors.transparent,
            appBar: appBar,
            body: SafeArea(child: body),
          ),
        ],
      ),
    );
  }
}
