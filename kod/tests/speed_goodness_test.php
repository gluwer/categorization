<?php
@ini_set('memory_limit', '100M');
@ini_set('max_execution_time', 0);
require_once(dirname(__FILE__).'/../lib/stoper.php');
require_once(dirname(__FILE__).'/../lib/winnow.php');
require_once(dirname(__FILE__).'/../lib/dcmplus.php');
require_once(dirname(__FILE__).'/../lib/icnn.php');
require_once(dirname(__FILE__).'/../lib/knn.php');

// konfiguracja testu
$suppressThreshold = false;
$initialComments = 50;
$maxAnalizedComments = 1000;
$TestDirTemp = 'Wtest_%d_%d_%d'; // prog, pocz. koment., maks analiz
$resultsFileTemp = 'results_%d_%s_%d.txt'; // idc, klasyf., nr testu
$timeFileTemp = 'speed_%d.txt'; // idc

////// konfiguracja podstawowa i g³ówne tablice
$db = DBHelper::connect('localhost','swk', '', 'swk');
$dictionaries = dirname(__FILE__).'/../dict';
$copyUnknown = false;
$subTests = 1;
$times = array();
$curTimes = array();
$curResults = array();
$partialResults = array();
$summedResults = array();
$classObj = array();

// konfiguracja klasyfikatorów
$classifiers = array(
'Winnow'=> array(
  '1'=> array(
    'alpha'=>2,
    'beta'=>0.5,
    'irrelevant1'=>0.005,
    'irrelevant2'=>2,
    'threshold'=>0.5
  ),
  '2'=> array(
    'alpha'=>2,
    'beta'=>0.5,
    'irrelevant1'=>0.005,
    'irrelevant2'=>2,
    'threshold'=>0.5
  ),
  '3'=> array(
    'alpha'=>2,
    'beta'=>0.5,
    'irrelevant1'=>0.005,
    'irrelevant2'=>2,
    'threshold'=>0.5
  ),
),
'DCMPlus'=> array(
  '1'=> array(
    'threshold'=>0.05
  ),
  '2'=> array(
    'threshold'=>0.02
  ),
  '3'=> array(
    'threshold'=>0.1
  ),
),
'Icnn'=> array(
  '1'=> array(
    'threshold'=>0.5,
    'similarity'=>0.05
  ),
  '2'=> array(
    'threshold'=>0.2,
    'similarity'=>0.05
  ),
  '3'=> array(
    'threshold'=>0.8,
    'similarity'=>0.05
  ),
),
'Knn'=> array(
  '1'=> array(
    'threshold'=>0.5,
    'k'=>10,
    'maxComments'=>200
  ),
  '2'=> array(
    'threshold'=>0.2,
    'k'=>10,
    'maxComments'=>200
  ),
  '3'=> array(
    'threshold'=>0.7,
    'k'=>10,
    'maxComments'=>200
  ),
)
);

// utwórz folder dla testów
$tmps = ($suppressThreshold?0:1);
$directory = sprintf($TestDirTemp, $tmps, $initialComments, $maxAnalizedComments);
if (!file_exists($directory)) {
  mkdir($directory);
}

// wszystkie testy wykonaj $subTests razy
for($sub = 1; $sub <= $subTests; ++$sub) {
  // poszczególne zestawy komentarzy
  for($idc = 1; $idc <= 3; ++$idc) {
    $curTimes = array();
    $classObj = array();
    $partialResults["{$sub}_$idc"] = array();
    $summedResults["{$sub}_$idc"] = array();
    if ($sub == 1) {
      $times["$idc"] = array();
    }
    // pobierz komentarze inicjalizuj±ce
    $posInit = DBHelper::getAssoc($db, "SELECT id, comment FROM comment_$idc WHERE type = 'OK' ORDER BY id LIMIT $initialComments");
    $negInit = DBHelper::getAssoc($db, "SELECT id, comment FROM comment_$idc WHERE type = 'ER' ORDER BY id LIMIT $initialComments");
    // utwórz obiekty klasyfikatorów, obiekty czasomierzy
    // zainicjalizuj tablice wyników, dokonaj inicjalizacji
    foreach ($classifiers as $k => $v) {
      if ($suppressThreshold) {
        $v["$idc"]['threshold'] = 0.0;
      }
      $tmpc = new ReflectionClass($k);
      $classObj[$k] = $tmpc->newInstance($db, $dictionaries, $idc, $copyUnknown, $v["$idc"]);
      $curTimes[$k] = new Timer();
      $classObj[$k]->doInit($posInit, $negInit);
      $curTimes[$k]->set('doInit');
      $curResults[$k] = array('TP'=>0,'TN'=>0,'FP'=>0,'FN'=>0,'UC'=>0);
    }
    unset($posInit, $negInit);
    $ii = 0;
    // rozpoczêcie standardowych testów...
    $res = $db->query("SELECT * FROM comment_$idc WHERE type IN ('OK','ER') AND id NOT BETWEEN 1 AND $initialComments AND id NOT BETWEEN 501 AND ".(500+$initialComments)." ORDER BY RAND() LIMIT $maxAnalizedComments");
    while ($row = $res->fetch_row()) {

      list($id, $comment, $type) = $row;
      ++$ii;
      foreach ($curTimes as $v) {
      	$v->set('start');
      }
      $prep_comment = $classObj['Winnow']->doPreparation($comment);
      foreach ($curTimes as $v) {
      	$v->set('doPreparation');
      }
      foreach ($classObj as $k => $v) {
        $curTimes[$k]->set('start');
        if ($prep_comment === false) {
          $score = -1;
        } else {
          $score = $v->doClassify($prep_comment, true);
        }
        $curTimes[$k]->set('doClassify');
        switch ($score) {
          case -1:
            if ($type == 'ER') {
              $curResults[$k]['TN']++;
            } else {
              $curResults[$k]['FN']++;
              if ($prep_comment !== false) {
                $v->doUpdate($prep_comment, true, true);
                $curTimes[$k]->set('doUpdate');
              }
            }
            break;
          case 1:
            if ($type == 'OK') {
              $curResults[$k]['TP']++;
            } else {
              $curResults[$k]['FP']++;
              $v->doUpdate($prep_comment, false, true);
              $curTimes[$k]->set('doUpdate');
            }
            break;
          default:
            if ($type == 'OK') {
              $v->doUpdate($prep_comment, true, true);
              $curTimes[$k]->set('doUpdate');
            } else {
              $v->doUpdate($prep_comment, false, true);
              $curTimes[$k]->set('doUpdate');
            }
            $curResults[$k]['UC']++;
            break;
        }
        if (($ii % 50) == 0) {
          $partialResults["{$sub}_$idc"]["$ii"][$k] = $curResults[$k];
          $curResults[$k] = array('TP'=>0,'TN'=>0,'FP'=>0,'FN'=>0,'UC'=>0);
        }
      }
      echo '.';
      if (($ii % 50) == 0) {
        echo "\n";
      }
      fflush(STDOUT);
    }
    $res->free();
    echo "\n";
    foreach ($classObj as $k => &$v) {
    	$v = null;
    	$partialResults["{$sub}_$idc"]["$ii"][$k] = $curResults[$k];
      // sumuj wyniki cz±stkowe na ca³o¶ciowe i wylicz statystykê cz±stkowych
      foreach ($partialResults["{$sub}_$idc"] as &$vv) {
        foreach ($vv[$k] as $kk => $vvv) {
          $summedResults["{$sub}_$idc"][$k][$kk] += $vvv;
        }
        $vv[$k]['precision'] = (float)$vv[$k]['TP'] / ($vv[$k]['TP'] + $vv[$k]['FP']);
        $vv[$k]['recall'] = (float)$vv[$k]['TP'] / ($vv[$k]['TP'] + $vv[$k]['FN']);
        $vv[$k]['F1'] = (2 * $vv[$k]['precision'] * $vv[$k]['recall']) / ($vv[$k]['precision'] + $vv[$k]['recall']);
        $tmpSum = $vv[$k]['TP'] + $vv[$k]['TN'] + $vv[$k]['FP'] + $vv[$k]['FN'] + $vv[$k]['UC'];
        $vv[$k]['accuracyP'] = (float)$vv[$k]['TP'] / $tmpSum;
        $vv[$k]['accuracyN'] = (float)$vv[$k]['TN'] / $tmpSum;
        $vv[$k]['accuracy'] = $vv[$k]['accuracyP'] + $vv[$k]['accuracyN'];
        $vv[$k]['errorP'] = (float)$vv[$k]['FP'] / $tmpSum;
        $vv[$k]['errorN'] = (float)$vv[$k]['FN'] / $tmpSum;
        $vv[$k]['error'] = $vv[$k]['errorP'] + $vv[$k]['errorN'];
        $vv[$k]['unclassified'] = (float)$vv[$k]['UC'] / $tmpSum;
    	}
    	if (!isset($times["$idc"][$k])) {
    	  $times["$idc"][$k] = array();
    	}
    	$times["$idc"][$k]['doInit'] += $curTimes[$k]->get('doInit');
    	$times["$idc"][$k]['doPreparation'] += $curTimes[$k]->get('doPreparation');
    	$times["$idc"][$k]['doClassify'] += $curTimes[$k]->get('doClassify');
    	$times["$idc"][$k]['doUpdate'] += $curTimes[$k]->get('doUpdate');
    	$vv = &$summedResults["{$sub}_$idc"][$k];
      $vv['precision'] = (float)$vv['TP'] / ($vv['TP'] + $vv['FP']);
      $vv['recall'] = (float)$vv['TP'] / ($vv['TP'] + $vv['FN']);
      $vv['F1'] = (2 * $vv['precision'] * $vv['recall']) / ($vv['precision'] + $vv['recall']);
      $tmpSum = $vv['TP'] + $vv['TN'] + $vv['FP'] + $vv['FN'] + $vv['UC'];
      $vv['accuracyP'] = (float)$vv['TP'] / $tmpSum;
      $vv['accuracyN'] = (float)$vv['TN'] / $tmpSum;
      $vv['accuracy'] = $vv['accuracyP'] + $vv['accuracyN'];
      $vv['errorP'] = (float)$vv['FP'] / $tmpSum;
      $vv['errorN'] = (float)$vv['FN'] / $tmpSum;
      $vv['error'] = $vv['errorP'] + $vv['errorN'];
      $vv['unclassified'] = (float)$vv['UC'] / $tmpSum;
      // wygeneruj wyniki testowe i zapisz je
      $str = "Wyniki cz±stkowe (dla ka¿dej 50-ki) i ³±czne dla klasyfikatora $k, pakiet komentarzy $idc, test $sub\n\n";
      $str .= "PoNk | TP  | TN  | FP  | FN  | UC  | Precis | Recall |  F1    | accurP | accurN | accura | errorP | errorN | error  | unclas\n";
      $template = "%4s | %3d | %3d | %3d | %3d | %3d | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f\n";
      foreach ($partialResults["{$sub}_$idc"] as $kk => &$vv) {
        $t = &$vv[$k];
        $str .= sprintf($template, $kk, $t['TP'], $t['TN'], $t['FP'], $t['FN'], $t['UC'], 100*$t['precision'], 100*$t['recall'], 100*$t['F1'], 100*$t['accuracyP'], 100*$t['accuracyN'], 100*$t['accuracy'], 100*$t['errorP'], 100*$t['errorN'], 100*$t['error'], 100*$t['unclassified']);
      }
      $t = &$summedResults["{$sub}_$idc"][$k];
      $str .= sprintf("\nSUMA | %3d | %3d | %3d | %3d | %3d | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f | %6.2f\n",
      $t['TP'], $t['TN'], $t['FP'], $t['FN'], $t['UC'], 100*$t['precision'], 100*$t['recall'], 100*$t['F1'], 100*$t['accuracyP'], 100*$t['accuracyN'], 100*$t['accuracy'], 100*$t['errorP'], 100*$t['errorN'], 100*$t['error'], 100*$t['unclassified']);
      file_put_contents($directory.'/'.sprintf($resultsFileTemp, $idc, $k, $sub), $str);
    }
  }
}
// zapisz wyniki czasowe do odpowiednich plików
for($idc = 1; $idc <= 3; ++$idc) {
  $str = "¦rednia suma czasów wykonania poszczególnych metod dla pakietu komentarzy $idc\n\n";
  $str .= "Metoda  ";
  foreach ($times["$idc"]['Winnow'] as $k => $v) {
  	$str .= sprintf("| %-15s ", $k);
  }
  $str .= "\n";
  foreach ($times["$idc"] as $k => &$v) {
    $str .= sprintf("%-7s ", $k);
    foreach ($v as $kk => $vv) {
  	  $str .= sprintf("| %-15s ", Timer::format($vv/$subTests));
    }
    $str .= "\n";
  }
  file_put_contents($directory.'/'.sprintf($timeFileTemp, $idc), $str);
}















?>
