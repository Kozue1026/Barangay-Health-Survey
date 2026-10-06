import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/survey.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';

/// Answer screen: multiple_choice / yes_no / rating 1-5 / short_answer + Other-specify.
class TakeSurveyScreen extends StatefulWidget {
  final Survey survey;
  const TakeSurveyScreen({super.key, required this.survey});
  @override
  State<TakeSurveyScreen> createState() => _TakeSurveyScreenState();
}

class _TakeSurveyScreenState extends State<TakeSurveyScreen> {
  List<SurveyQuestion> _qs = [];
  bool _loading = true;
  bool _gone = false;
  final Map<String, String> _answers = {}; // questionId -> choiceId or text
  final Map<String, String> _other = {}; // questionId -> other text
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      _qs = await context.read<Session>().api.surveyDetail(widget.survey.id);
      _gone = false;
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      final m = '$e'.toLowerCase();
      if (m.contains('no longer available') || m.contains('not found')) {
        _qs = [];
        _gone = true;
      } else {
        _qs = [];
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    // Required check (web parity: take_survey required flag)
    for (final q in _qs) {
      if (q.required && (_answers[q.id]?.isEmpty ?? true)) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Please answer question: ${q.text}')));
        return;
      }
    }
    final ok = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
              title: const Text('Submit Survey'),
              content: const Text('Are you sure? This action cannot be undone.'),
              actions: [
                TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
                ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Yes, Submit')),
              ],
            ));
    if (ok != true) return;
    setState(() => _sending = true);
    try {
      // Revalidate: survey may have been inactivated while answering.
      try {
        final fresh = await context.read<Session>().api.surveyDetail(widget.survey.id);
        if (fresh.isEmpty) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('This survey is no longer available. Pull to refresh the list.')));
          }
          return;
        }
      } catch (_) {}
      final payload = <String, String>{};
      for (final q in _qs) {
        var v = _answers[q.id] ?? '';
        if (q.type == 'multiple_choice') {
          final choice = q.choices.where((c) => c.id == v).firstOrNull;
          if (choice != null && choice.text.toLowerCase().startsWith('other') && (_other[q.id]?.isNotEmpty == true)) {
            payload[q.id] = 'Other: ${_other[q.id]}';
            continue;
          }
        }
        payload[q.id] = v;
      }
      await context.read<Session>().api.submitSurvey(widget.survey.id, payload);
      if (!mounted) return;
      showDialog(
          context: context,
          builder: (_) => AlertDialog(
                title: const Text('Survey Submitted!'),
                content: Text('Thank you for participating in ${widget.survey.title}.'),
                actions: [ElevatedButton(onPressed: () { Navigator.pop(context); Navigator.pop(context); Navigator.pop(context); }, child: const Text('Back to Surveys'))],
              ));
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$e'.replaceFirst('Exception: ', ''))));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_gone && !_loading) {
      return NavyScaffold(
        appBar: AppHeader(title: widget.survey.title, showBack: true),
        body: RefreshIndicator(
          onRefresh: _load,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.symmetric(vertical: 14),
            child: WhiteCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Survey no longer available',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('The admin deactivated or deleted this survey. Pull down to check again, or go back to the list.',
                      style: TextStyle(color: Colors.grey)),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Back to Surveys'),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }
    return NavyScaffold(
      appBar: AppHeader(title: widget.survey.title, showBack: true),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Colors.white))
          : RefreshIndicator(
              onRefresh: _load,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(vertical: 14),
                child: WhiteCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(widget.survey.title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                    Text(widget.survey.description, style: const TextStyle(color: Colors.grey)),
                    const Divider(height: 24),
                    ..._qs.asMap().entries.map((e) => _qWidget(e.key + 1, e.value)),
                    const SizedBox(height: 16),
                    ElevatedButton(
                        onPressed: _sending ? null : _submit,
                        child: Text(_sending ? 'Submitting...' : 'Submit Survey')),
                  ],
                ),
              ),
            ),
          ),
    );
  }

  Widget _qWidget(int n, SurveyQuestion q) {
    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      padding: const EdgeInsets.only(bottom: 14),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFEEEEEE)))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('$n. ${q.text} ${q.required ? '*' : ''}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        const SizedBox(height: 8),
        if (q.type == 'multiple_choice')
          ...q.choices.map((c) {
            final isOther = c.text.toLowerCase().startsWith('other');
            return Column(children: [
              RadioListTile<String>(
                value: c.id, groupValue: _answers[q.id], title: Text(c.text),
                onChanged: (v) => setState(() => _answers[q.id] = v ?? ''),
              ),
              if (isOther && _answers[q.id] == c.id)
                Padding(padding: const EdgeInsets.only(left: 32, right: 8, bottom: 8),
                    child: TextField(decoration: const InputDecoration(hintText: 'Please specify...'),
                        onChanged: (v) => _other[q.id] = v)),
            ]);
          }),
        if (q.type == 'yes_no')
          ...['Yes', 'No'].map((v) => RadioListTile<String>(
              value: v, groupValue: _answers[q.id], title: Text(v),
              onChanged: (x) => setState(() => _answers[q.id] = x ?? ''))),
        if (q.type == 'rating')
          Wrap(spacing: 8, children: List.generate(5, (i) {
            final v = '${i + 1}';
            final sel = _answers[q.id] == v;
            return ChoiceChip(label: Text(v), selected: sel, onSelected: (_) => setState(() => _answers[q.id] = v));
          })),
        if (q.type == 'short_answer')
          TextField(maxLines: 3, maxLength: 500, decoration: const InputDecoration(hintText: 'Type your answer...'),
              onChanged: (v) => _answers[q.id] = v),
      ]),
    );
  }
}
