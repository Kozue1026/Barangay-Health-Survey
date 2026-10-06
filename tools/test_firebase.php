<?php
require __DIR__ . '/../config/firebase.php';
$pass = 0; $fail = 0;
function ok($name, $cond, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "PASS $name $extra\n"; }
    else { $fail++; echo "FAIL $name $extra\n"; }
}
// 1. Read nodes
$admins = fb_all('admins'); ok('read admins', count($admins) >= 1, 'count='.count($admins));
$residents = fb_all('residents'); ok('read residents', is_array($residents), 'count='.count($residents));
$surveys = fb_all('surveys'); ok('read surveys', is_array($surveys), 'count='.count($surveys));
// 2. Auth verify (admin password is 'password' per seed hash for admin/RES? check)
$adm = fb_find_one('admins', 'username', 'admin');
ok('admin lookup', (bool)$adm);
if ($adm) {
    ok('admin hash is bcrypt', (bool)preg_match('/^\$2[ay]\$/', fb_pass_hash($adm)));
}
// resident lookup by number
$firstRes = null; foreach ($residents as $id => $r) { $firstRes = $r + ['_id' => (string)$id]; break; }
if ($firstRes) {
    ok('resident has number', !empty($firstRes['resident_number']), $firstRes['resident_number']);
    // password should verify against resident_number (default) OR 'password'
    $h = fb_pass_hash($firstRes);
    $pwOk = password_verify($firstRes['resident_number'], $h) || password_verify('password', $h);
    ok('resident password plausible', $pwOk);
}
// 3. CRUD roundtrip on _test node
$tid = 'crud_' . time();
fb_set('/_test/' . $tid, ['a' => 1, 'ts' => fb_now()]);
ok('create', fb_get('/_test/' . $tid)['a'] === 1);
fb_update('/_test/' . $tid, ['a' => 2]);
ok('update', fb_get('/_test/' . $tid)['a'] === 2);
fb_remove('/_test/' . $tid);
ok('delete', fb_get('/_test/' . $tid) === null);
// 4. Survey response flow (temp survey)
$sid = fb_next_id('surveys');
fb_set_rec('surveys', $sid, ['title' => 'TEST', 'status' => 'inactive', 'created_at' => fb_now(), 'updated_at' => fb_now()]);
$qid = fb_next_id('survey_questions');
fb_set_rec('survey_questions', $qid, ['survey_id' => $sid, 'question_text' => 'Q?', 'question_type' => 'yes_no', 'question_order' => 1, 'required' => 1, 'created_at' => fb_now()]);
$cid = fb_next_id('survey_choices');
fb_set_rec('survey_choices', $cid, ['question_id' => $qid, 'choice_text' => 'Opt', 'choice_order' => 1, 'created_at' => fb_now()]);
list($qs, $res) = fb_question_results($sid);
ok('question_results', count($qs) === 1);
// cleanup
fb_delete_rec('survey_choices', $cid);
fb_delete_rec('survey_questions', $qid);
fb_delete_rec('surveys', $sid);
ok('cleanup', fb_get_rec('surveys', $sid) === null);
// 5. Counters sane
foreach (['admins','residents','surveys','responses'] as $t) {
    $c = fb_get('/_counters/' . $t);
    ok("counter $t", is_array($c) && isset($c['next_id']));
}
echo "RESULT pass=$pass fail=$fail\n";
exit($fail ? 1 : 0);
