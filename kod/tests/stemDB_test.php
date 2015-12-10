<?
header('Content-type: text/plain');
ini_set('max_execution_time',180);
require_once('../lib/db.php');
require_once('../lib/simple_validate.php');
require_once('../lib/stoper.php');
require_once('../lib/tokenizer.php');
require_once('../fsa/fsaa-opt.php');
require_once('../fsa/fsal-opt.php');
require_once('../fsa/fsas-opt.php');

// konfiguracja //
$cfg_serv = 'localhost';
$cfg_user = 'swk';
$cfg_pass = '';
$cfg_db = 'swk';
$cfg_tab = '3';
//////////////////

// po³±cz z baz± danych
$db = DBHelper::connect($cfg_serv,$cfg_user, $cfg_pass, $cfg_db);
// zainicjalizuj pozosta³e klasy
$validation = new Validation('../dict/vulgarism.txt');
$tokenizer = new Tokenizer('../dict/stoplist.txt');
$fsaa = new Fsaa('../dict/lort_acc_full.fsa');
$fsas = new Fsas('../dict/lort_acc_full.fsa');
$fsal = new Fsal('../dict/llems_full.fsa');
$pspell_config = pspell_config_create("pl");
pspell_config_ignore($pspell_config, 4);
pspell_config_mode($pspell_config, PSPELL_FAST);
pspell_config_runtogether($pspell_config, false);
$pspell_link = pspell_new_config($pspell_config);

// uruchomienie stopera, rozpoczêcie zbierania danych czasowych
$stoper = new Timer();

// pobierz zbiór wyników
$res = $db->query("SELECT * FROM comment_$cfg_tab WHERE type = 'OK' ORDER BY id");
while (($row = $res->fetch_row())) {
  list($id, $comment, $type) = $row;
  //echo $comment;
  $stoper->set('query');
  // sprawdzenie email i WWW
  if (Validation::findEmail($comment) || Validation::findWWW($comment)) {
    echo 'E'.$id.'--'.$comment.'==='.implode(', ',$tok_comment)."\n";
    $stoper->set('email');
    continue;
  }
  $stoper->set('email');

  // docelowa tablica s³ów
  $tok_comment = array();
  // tokenizacja
  $tok_comment1 = $tokenizer->tokenize($comment);
  // zdjêcie informacji o potencjalnych wulgaryzmach
  $prop_vulg = intval(array_pop($tok_comment1));
  $stoper->set('tokens');
/*  foreach ($tok_comment1 as $w) {
    if (pspell_check($pspell_link, $w)) {
      $tok_comment[] = $w;
    } elseif (($temp = $fsaa->accent_word($w))) {
      $tok_comment = array_merge($tok_comment, $temp);
    } elseif (strlen($w)>3 && count($temp = pspell_suggest($pspell_link,$w))>0) {
      $tok_comment = array_merge($tok_comment, array_slice(array_filter($temp,"pspell_filter"),0,5));
    } else {
      //$tok_comment[] = $w;
    }
  }
  $stoper->set('accents&spell');*/
  foreach ($tok_comment1 as $w) {
    if ($fsas->find_word($w)) {
      $tok_comment[] = $w;
    } elseif (($temp = $fsaa->accent_word($w))) {
      $tok_comment = array_merge($tok_comment, $temp);
    } elseif (strlen($w)>3 && ($temp = $fsas->spell_word($w))) {
      $tok_comment = array_merge($tok_comment, array_slice($temp, 0, 5)); // $tokenizer->checkOnStoplist($temp));
    } else {
      //$tok_comment[] = $w;
    }
  }
  unset($tok_comment1);
  $stoper->set('accents&spell');
  // lematyzacja
  $tok_comment1 = array();
  if (count($tok_comment)>0) {
    foreach ($tok_comment as $w) {
      $temp = $fsal->lematize($w);
      if ($temp !==false) {
        $tok_comment1 = array_merge($tok_comment1, $temp);
      }
    }
    $tok_comment = array_unique($tok_comment1);
  }
  unset($tok_comment1);
  $stoper->set('lems');

  // sprawdzenie, czy s± wulgaryzmy
  if ($validation->findVulgarism($tok_comment) || $prop_vulg>0) {
    echo 'V'.$id."-$prop_vulg-".$comment.'==='.implode(', ',$tok_comment)."\n";
    $stoper->set('vulg');
    continue;
  }
  $stoper->set('vulg');
  //echo $id.'--'.$comment.'==='.implode(', ',$tok_comment)."\n";
}

$res->close();
$db->close();
print_r($stoper->endAndList(true));

function pspell_filter($w) {
  if (strpos($w,' ',1) !== false) return false;
  if (strpos($w,'-',1) !== false) return false;
  return true;
}

?>