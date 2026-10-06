import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/survey.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';
import 'survey_detail_screen.dart';

/// Survey list — auto-refreshes when admin creates/inactivates surveys.
/// Polls sync_check every 15s + reloads on appear + pull-to-refresh.
class SurveyListScreen extends StatefulWidget {
  const SurveyListScreen({super.key});
  @override
  State<SurveyListScreen> createState() => _SurveyListScreenState();
}

class _SurveyListScreenState extends State<SurveyListScreen> with WidgetsBindingObserver {
  List<Survey> _items = [];
  bool _loading = true;
  String _search = '';
  String _filter = 'all';
  Timer? _poll;
  int? _version;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _poll = Timer.periodic(const Duration(seconds: 15), (_) => _checkSync());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _load();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _load();
  }

  Future<void> _load() async {
    try {
      final api = context.read<Session>().api;
      final items = await api.surveys(search: _search, filter: _filter);
      if (mounted) setState(() { _items = items; _loading = false; });
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      if (mounted) setState(() { _items = []; _loading = false; });
    }
  }

  Future<void> _checkSync() async {
    if (!mounted) return;
    try {
      final api = context.read<Session>().api;
      final v = await api.syncCheck(context.read<Session>().resident?.id ?? '');
      final sv = v['surveys_version'];
      final svi = sv is int ? sv : int.tryParse('$sv') ?? 0;
      if (_version != null && svi != _version) await _load();
      _version = svi;
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return NavyScaffold(
      appBar: const AppHeader(title: 'Community Health Surveys', showBack: true),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: WhiteCard(
              child: Column(children: [
                TextField(
                  decoration: const InputDecoration(
                      hintText: 'Search surveys...', prefixIcon: Icon(Icons.search)),
                  onChanged: (v) { _search = v; _load(); },
                ),
                const SizedBox(height: 10),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'all', label: Text('All')),
                    ButtonSegment(value: 'active', label: Text('Pending')),
                    ButtonSegment(value: 'completed', label: Text('Completed')),
                  ],
                  selected: {_filter},
                  onSelectionChanged: (s) { _filter = s.first; _load(); },
                ),
              ]),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: Colors.white))
                : _items.isEmpty
                    ? Center(
                        child: RefreshIndicator(
                          onRefresh: _load,
                          child: SingleChildScrollView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            child: Container(
                              padding: const EdgeInsets.symmetric(vertical: 60),
                              alignment: Alignment.center,
                              child: const Text('No surveys found. Pull to refresh.',
                                  style: TextStyle(color: Colors.white70)),
                            ),
                          ),
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView.builder(
                        padding: const EdgeInsets.only(bottom: 20),
                        itemCount: _items.length,
                        itemBuilder: (_, i) {
                          final s = _items[i];
                          return Container(
                            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                                color: Colors.white, borderRadius: BorderRadius.circular(14)),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(children: [
                                  Chip(label: Text(s.category, style: const TextStyle(fontSize: 11))),
                                  const Spacer(),
                                  Chip(
                                    label: Text(s.completed ? 'Completed' : 'Pending',
                                        style: const TextStyle(fontSize: 11, color: Colors.white)),
                                    backgroundColor: s.completed ? Colors.green : Colors.orange,
                                  ),
                                ]),
                                Text(s.title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
                                Text(s.description, maxLines: 2, overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(color: Colors.grey, fontSize: 13)),
                                Text('Closes: ${s.closingDate} • ${s.questionCount} questions',
                                    style: const TextStyle(fontSize: 12, color: Colors.grey)),
                                const SizedBox(height: 10),
                                SizedBox(
                                  width: double.infinity,
                                  child: ElevatedButton(
                                    onPressed: s.completed
                                        ? null
                                        : () => Navigator.push(context,
                                            MaterialPageRoute(builder: (_) => SurveyDetailScreen(survey: s))).then((_) => _load()),
                                    child: Text(s.completed ? 'Completed ✓' : 'Take Survey'),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
          ),
        ],
      ),
    );
  }
}
