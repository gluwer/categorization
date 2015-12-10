<?php
echo '<?xml version="1.0" encoding="iso-8859-2"?>';
$acomments = array("1"=>"gry.pl","2"=>"gsmonline.pl","3"=>"przepisy kucharskie");
$aclassifiers = array("all"=>"wszystkie","Winnow"=>"Balanced Winnow","DCMPlus"=>"DCM+","Icnn"=>"ICNN","Knn"=>"k-NN");
$aresult = array("-1"=>"niepoprawny","0"=>"nierozstrzygnięty","1"=>"poprawny");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>Demonstracja systemu weryfikacji komentarzy</title>
  <meta name="AUTHOR" content="Rafał Jońca" />
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-2" />
  <style type="text/css">
h2 {font : normal 20px Arial;}
h4 {font : italic 14px Arial;}
h5 {font : bold 12px Arial;}
i {color : #0000dd;}
select {background : #e0e0e0;}
textarea {background : #e0e0e0;}
input {background : #e0e0e0;}
body, table {font-family : Tahoma, Verdana, Arial;font-size : 12px;text-align : center;}
table {margin-left : auto;margin-right:auto;text-align: center; }
.tabWyn {border-width : 2px;border-collapse : collapse;}
.blad {color : #f00000; font-weight: bold;}
th {border-bottom-width : 2px;}

</style>
</head>
<div>
<h2>Demonstracja systemu weryfikacji komentarzy<sup>*</sup></h2>
<!--Klasyfikuj-->
<?php
if (isset($_POST['verify']) && array_key_exists($_POST['set'], $acomments) && array_key_exists($_POST['classifier'], $aclassifiers) && trim($_POST['comment'])!='') {

    // includy
    @ini_set('memory_limit', '25M');
    require_once(dirname(__FILE__).'/../lib/stoper.php');
    require_once(dirname(__FILE__).'/../lib/winnow.php');
    require_once(dirname(__FILE__).'/../lib/dcmplus.php');
    require_once(dirname(__FILE__).'/../lib/icnn.php');
    require_once(dirname(__FILE__).'/../lib/knn.php');

// konfiguracja testu
$suppressThreshold = ($_POST['reject']=='1')?false:true;
$db = DBHelper::connect('localhost','test', '123qwe', 'test');
$dictionaries = dirname(__FILE__).'/../dict';
$results = array();
$times = array();
$values = array();
$classObj = array();
$prepText = null;
$prepResult = array();

// konfiguracja klasyfikatorów
$pclassifiers = array(
'Winnow'=> array(
  '1'=> array('alpha'=>2, 'beta'=>0.5, 'irrelevant1'=>0.005, 'irrelevant2'=>2, 'threshold'=>0.5),
  '2'=> array('alpha'=>2, 'beta'=>0.5, 'irrelevant1'=>0.005, 'irrelevant2'=>2, 'threshold'=>0.5),
  '3'=> array('alpha'=>2, 'beta'=>0.5, 'irrelevant1'=>0.005, 'irrelevant2'=>2, 'threshold'=>0.5)),
'DCMPlus'=> array(
   '1'=> array('threshold'=>0.05),
  '2'=> array('threshold'=>0.02),
  '3'=> array('threshold'=>0.1)),
'Icnn'=> array(
  '1'=> array('threshold'=>0.5, 'similarity'=>0.05),
  '2'=> array('threshold'=>0.2, 'similarity'=>0.05),
  '3'=> array('threshold'=>0.8, 'similarity'=>0.05)),
'Knn'=> array(
  '1'=> array('threshold'=>0.5, 'k'=>10, 'maxComments'=>200),
  '2'=> array('threshold'=>0.2, 'k'=>10, 'maxComments'=>200),
  '3'=> array('threshold'=>0.7, 'k'=>10, 'maxComments'=>200))
);

// utwórz tablicę z parametrami do testu
$classifiers = array();
if ($_POST['classifier']!='all') {
    $classifiers[$_POST['classifier']] = $pclassifiers[$_POST['classifier']][$_POST['set']];
    if ($suppressThreshold) {
        $classifiers[$_POST['classifier']]['threshold'] = 0.0;
    }
} else {
    foreach ($pclassifiers as $k => $v) {
    	$classifiers[$k] = $v[$_POST['set']];
    	if ($suppressThreshold) {
            $classifiers[$k]['threshold'] = 0.0;
        }
    }
}

// utwórz obiekty klasyfikatorów, obiekty czasomierzy a następnie klasyfikuj
foreach ($classifiers as $k => $v) {
    $tmpc = new ReflectionClass($k);
    $classObj[$k] = $tmpc->newInstance($db, $dictionaries, $_POST['set'], false, $v);
    $time = new Timer();
    if (is_null($prepText)) {
        $time->set('start');
        $prepText = $classObj[$k]->doPreparation($_POST['comment']);
        $time->set('doPreparation');
        $prepResult['time'] = '<b>'.$time->format($time->get('doPreparation')).'</b>';
        if ($prepText === false) {
            break;
        }
    }
    $time->set('start');
    $results[$k] = $classObj[$k]->doClassify($prepText, true);
    $time->set('doClassify');
    $times[$k] = $time->format($time->get('doClassify'));
}
unset($classObj);
$prepResult['org'] = $_POST['comment'];
?>
<!--<h4>Testowany komentarz</h4>
<textarea readonly="true" cols="60" rows="15" name="comment" id="comment" title="Treść komentarza">
<?php
//echo $_POST['comment'];
?>
</textarea>-->
<h4>Wyniki dla zbioru komentarzy <?php echo $acomments[$_POST['set']]; ?> przy <?php if ($_POST['reject']=='1') echo 'włączonym'; else echo 'wyłączonym'; ?> odmawianiu niepewnych klasyfikacji</h4>
<?php
if (isset($prepResult['error'])) {
echo '<span class="blad">'.$prepResult['error'].'</span><br />';
} else { ?>
<h5>Przygotowanie wstępne</h5>
<table border="2" cellpadding="3" class="tabWyn" >
  <thead align="center">
    <tr>
      <th width="25%">Etap</th>
      <th width="75%">Wynik</th>
    </tr>
  </thead>
  <tbody align="center">
  <?php
    foreach (array('org'=>'Oryginalny komentarz','tok1'=>'Po tokenizacji','tok2'=>'Po korekcie','tok3'=>'Po lematyzacji','time'=>'<b>Czas wykonania</b>') as $k => $v) {
      echo '<tr><td>';
      echo $v;
      echo '</td><td>';
      if (!is_array($prepResult[$k])) {
          echo $prepResult[$k];
      } else {
          echo implode(', ',$prepResult[$k]);
      }
      echo '</td></tr>';
    }
  ?>
  </tbody>
</table>
<h5>Właściwa klasyfikacja</h5>
<table border="2" cellpadding="3" class="tabWyn" >
  <thead align="center">
    <tr>
      <th width="25%">Algorytm</th>
      <th width="20%">Wynik</th>
      <th width="15%">Czas klas.</th>
      <th width="20%">Ocena poz.</th>
      <th width="20%">Ocena neg.</th>
    </tr>
  </thead>
  <tbody align="center">
  <?php
    foreach ($results as $k => $v) {
      echo '<tr><td>';
      echo $aclassifiers[$k];
      echo '</td><td>';
      echo $aresult[$v];
      echo '</td><td>';
      echo $times[$k];
      echo '</td><td>';
      echo number_format($values[$k]['p'],8,',','');
      echo '</td><td>';
      echo number_format($values[$k]['n'],8,',','');
      echo '</td></tr>';
    }
  ?>
  </tbody>
</table>
<?php
}
?>
<br />
<a href="demo.php">&lt;&lt;Wróć</a>

<!--Formularz dla demo-->
<?php
} else {
?>
Proszę wpisać treść komentarza poniżej i wybrać odpowiednie opcje.
<form accept-charset="iso-8859-2" action="demo.php" enctype="application/x-www-form-urlencoded" method="POST">
<table border="0">
<tr>
<td width="60%">
<?php
if (isset($_POST['verify'])) {
echo '<br /><span class="blad">Nie podano treści komentarza!</span><br />';
}
?>
<textarea cols="60" rows="15" name="comment" id="comment" title="Treść komentarza">
<?php
echo $_POST['comment'];
?>
</textarea>
</td>
<td width="40%">
<fieldset>
<legend>Opcje</legend>
Zbiór testowy: <select name="set" id="set">
<?php
foreach ($acomments as $k=>$v) {
	if ($_POST['set']==$k) $t = ' selected="true"'; else $t='';
	echo "<option id=\"$k\" value=\"$k\"$t>$v</option>";
}
?>
</select>
<br /><br />
Klasyfikator: <select name="classifier" id="classifier">
<?php
foreach ($aclassifiers as $k=>$v) {
	if ($_POST['classifier']==$k) $t = ' selected="true"'; else $t='';
	echo "<option id=\"$k\" value=\"$k\"$t>$v</option>";
}
?>
</select>
<br /><br />
<input type="hidden" name="reject" id="reject" value="0" />
<input type="checkbox" name="reject" id="reject" <?php if (!isset($_POST['reject']) || $_POST['reject']=='1') echo 'checked="true"'; ?> value="1" />&nbsp;Odmawiaj niepewnych klasyfikacji
<br />
</fieldset>
<br />
<input type="submit" value="Weryfikuj" name="verify" id="verify" />
</td>
</tr>
</table>
</form>
<?php
}
?>
<br /><br />
<sup>*</sup> Jońca Rafał: <i>System weryfikacji komentarzy na stronach WWW usprawniający pracę moderatora</i>, Promotor: Piotr Fabian, Politechnika Śląska, Gliwice, 2006
</div>
</body>
</html>
