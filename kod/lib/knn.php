<?php
require_once(dirname(__FILE__).'/classify-abs.php');

class Knn extends Classify {

  /**
   * Warto¶æ najwiêkszej ró¿nicy podobieñstwa, która jest dopuszczalna
   * by uznaæ grupê za jedn± ca³o¶æ.
   *
   * @var int
   */
  private $k = 10;

  /**
   * Ile maksymalnie komentarzy przechowywaæ.
   * Wykorzystywane przez algorytm czyszcz±cy z LRU.
   *
   * @var int
   */
  private $maxComments = 200;

  /**
   * Próg. Je¶li oceny ró¿ni± siê o mniej ni¿ tê warto¶æ,
   * komentarz uznaje siê za nierostrzygniêty.
   *
   * @var float
   */
  private $threshold = 0.5;

  /**
   * Konstrukor zapewniaj±cy ogóln± inicjalizacjê systemu przygotowywania danych:
   * tokenizer, korekta ortograficzna, uzupe³nianie polskich znaków, wulgaryzmy.
   *
   * @param mysqli $dbconn Obiekt po³±czenia z baz± danych u¿ywany w podklasach.
   * @param string $dictdir Folder ze s³ownikami, stoplistami itp.
   * @param int $idc Identyfikator wykorzystywanego zestawu komentarzy.
   * @param bool $copy_unknown Czy pozostawiaæ nierozpoznane wyrazy?
   * @param array $options Parametry konkretnego klasyfikatora jako tab. asocjacyjna.
   */
  function __construct($dbconn, $dictdir, $idc, $copy_unknown, $options = null) {
    parent::__construct($dbconn, $dictdir, $idc, $copy_unknown, null);
    if (is_null($options)) {
      return;
    }
    foreach ($options as $k => $v) {
    	$this->{$k} = $v;
    }
  }

  /**
   * Dokonuje klasyfikacji przekazanego wyrazu.
   * Zwraca -1, je¶li komentarz nale¿y odrzuciæ (wg klasyfikacji).
   * Zwraca 1, je¶li komentarz nale¿y zatwierdziæ (wg klasyfikacji).
   * Zwraca 0, gdy prawdopodobieñstwo pomy³ki jest du¿e.
   *
   * @param string $comment Komentarz do sklasyfikowania.
   * @param bool[optional] $prepared Czy nie trzeba przetwarzaæ komentarza?
   * @return int -1 -> nagatywny, 0 -> nieokre¶lony, 1 -> pozytywny
   */
  public function doClassify($comment, $prepared = false) {
    if ($prepared) {
      $tok_comment = &$comment;
    } else {
      $tok_comment = $this->doPreparation($comment);
      if ($tok_comment === false) {
        return -1;
      }
    }
    list ($scoreP, $scoreN) = $this->evalComment($tok_comment);
    if ($scoreP == 0.0 && $scoreN == 0.0) {
      return -1;
    }
    $mul = 1.0/($scoreP + $scoreN);
    $scoreP *= $mul;
    $scoreN *= $mul;
    if ($scoreP >= $scoreN+$this->threshold) {
      return 1;
    } else if ($scoreN >= $scoreP+$this->threshold) {
      return -1;
    }
    return 0;
  }

  /**
   * Dokonuje aktualizacji zbioru cech.
   *
   * @param mixed $comment Komentarz po przetworzeniu lub jeszcze jako tekst.
   * @param bool $positive Uaktualnij jako pozytywny czy negatywny.
   * @param bool[optional] $prepared Czy nie trzeba przetwarzaæ komentarza?
   */
  public function doUpdate($comment, $positive, $prepared = false) {
    if ($prepared) {
      $tok_comment = &$comment;
    } else {
      $tok_comment = $this->doPreparation($comment);
      if ($tok_comment === false) {
        return;
      }
    }
    $this->updateVector($tok_comment,$positive);
  }

  /**
   * Dokonuje wyliczenia oceny dla komentarza ze zbioru cech pozytywnych
   * i negatywnych.
   *
   * @param array $comment Tablica asocjacyjna wyrazów z wagami.
   * @return array Oceny komentarza (osobno suma negatywnych i pozytywnych).
   */
  protected function evalComment(array $comment) {
    $retContrib = array(0.0, 0.0);
    $model = array();
    // wylicz wagi i sumê ich kwadratów
    $comment = $this->evalWid($comment);
    $sumC2 = 0.0;
    foreach ($comment as $v) {
      $sum += $v * $v;
    }

    $similarities = array();
    // wylicz podobieñstwo do ka¿dego z komentarzy
    $sql = "SELECT subcat, pn FROM `datavect_$this->idc-4s`";
    $groups = DBHelper::getAssoc($this->dbconn, $sql);
    // przetwórz ka¿d± z grup osobno, je¶li podobieñstwo bêdzie wiêksze
    // od zapamiêtanej warto¶ci, dodaj miarê podobieñstwa do sumy
    $sql = "SELECT word, weight FROM `datavect_$this->idc-4p` WHERE subcat = ?";
    $stmt = $this->dbconn->prepare($sql);
    foreach ($groups as $subcat => $pn) {
    	$stmt->bind_param("i", $subcat);
    	$stmt->execute();
    	$stmt->bind_result($word, $weight);
    	$sumCG = 0.0;
    	$sumG2 = 0.0;
    	while ($stmt->fetch()) {
    	  if (array_key_exists($word, $comment)) {
    	    if ($weight != 0.0) {
    	      $sumCG += ($weight * $comment[$word]);
    	    }
    	  }
    	  $sumG2 += $weight * $weight;
    	}
      if (($sumC2 + $sumG2 - $sumCG) != 0.0) {
        $similarity = $sumCG / ($sumC2 + $sumG2 - $sumCG);
        $similarities[$subcat] = $similarity;
      }
    }
    $stmt->close();
    // wykorzystaj tylko $this->k najbli¿szych komentarzy
    arsort($similarities);
    $retContrib = array();
    $contributingGroups = array();
    $k = 0;
    reset($similarities);
    while ($k < $this->k && list($subcat, $sim) = each($similarities)) {
      $retContrib[(($groups[$subcat]=='p')?0:1)] += $sim;
      $contributingGroups[] = $subcat;
      ++$k;
    }

    // dla grup, które nie bra³y udzia³u w dopasowaniu, zwiêksz licznik
    // pozosta³ym ustaw licznik na 0
    $dbCG = "(".implode(",",$contributingGroups).")";
    $sql = "UPDATE `datavect_$this->idc-4s` SET last_act = last_act + 1
    WHERE subcat NOT IN $dbCG";
    $res = $this->dbconn->query($sql);
    $sql = "UPDATE `datavect_$this->idc-4s` SET last_act = 0
    WHERE subcat IN $dbCG";
    $res = $this->dbconn->query($sql);
    return $retContrib;
  }

  /**
   * Wylicza warto¶ci wid (wagi poszczególnych cech w dokumencie)
   * na podstawie czêsto¶ci wystêpowania wyrazów i innych
   * danych zebranych w BD dla wcze¶niejszych komentarzy.
   *
   * @param array $comment
   * @return array
   */
  protected function evalWid(array $comment) {
    $dbcom = "('".implode("','",array_keys($comment))."')";
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-4f` WHERE word IN $dbcom";
    $res = DBHelper::getAssoc($this->dbconn, $sql);
    $ret_array = array();
    $sum = 0.0;
    foreach ($comment as $k => $v) {
    	if (array_key_exists($k, $res)) {
    	  $val = log($v + 0.5) * $res[$k][0] * $res[$k][1];
    	} else {
    	  $val = log($v + 0.5);
    	}
    	$ret_array[$k] = $val;
    	$sum += $val;
    }
    if ($sum == 0.0) {
      $sum = 1.0;
    }
    $mul = 1/$sum;
    foreach ($ret_array as &$v) {
      $v *= $mul;
    }
    return $ret_array;
  }

  /**
   * Dokonuje bezpo¶redniej aktualizacji zbioru cech.
   *
   * @param array $comment Tablica asocjacyjna (wyraz => liczba wyst±pieñ).
   * @param bool $positive Zbiór cech pozytywnych lub negatywnych?
   */
  protected function updateVector(array $comment, $positive) {
    if ($positive) {
      $pn = 'p';
    } else {
      $pn = 'n';
    }
    // uaktualnij i pobierz Nk
    $sql = "UPDATE `datavect_$this->idc-4o` SET Nk$pn = Nk$pn+1";
    $this->dbconn->query($sql);
    $sql = "SELECT Nkp, Nkn FROM `datavect_$this->idc-4o`";
    list($Nkp, $Nkn) = DBHelper::getRow($this->dbconn, $sql);
    if ($positive) {
      $logNk = log($Nkp+1, 2);
    } else {
      $logNk = log($Nkn+1, 2);
    }
    $NkSum = $Nkp + $Nkn;
    $oneDivLogNK = 1/$logNk;
    // aktualizacja istniej±cych dfikp i dodanie nowych wyrazów
    $sql = "INSERT INTO `datavect_$this->idc-4f` (word,dfikp,dfikn,WCikp,WCikn,CCi,idf) VALUES ";
    $sql.= "(?, ";
    $sql.= $positive?"1, 0, ?, 0":"0, 1, 0, ?";
    $sql.= ", 2, 1)";
    $sql .= "ON DUPLICATE KEY UPDATE dfik$pn = dfik$pn + 1";
    $stmt = $this->dbconn->prepare($sql);
    $stmt->bind_param("sd", $k, $oneDivLogNK);
    foreach ($comment as $k => $v) {
      $stmt->execute();
    }
    $stmt->close();

    // uaktualnij zmienne statystyczne dla wszystkich wyrazów
    $sql = "UPDATE `datavect_$this->idc-4f`
    SET WCik$pn = LOG2(dfik$pn+1)/$logNk,
    CCi = 2 * LOG2((2 * GREATEST(dfikp,dfikn)) / (dfikp + dfikn)),
    idf = LOG($NkSum/(dfikp+dfikn));";
    $this->dbconn->query($sql);
    // pobierz wyliczone informacje statystyczne dla dodawanego komentarza
    $comment_words = array();
    $dbcom = "('".implode("','",array_keys($comment))."')";
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-4f` WHERE word IN $dbcom";
    $res = $this->dbconn->query($sql);
    while (($row = $res->fetch_row())) {
      list($word, $CCi, $idf) = $row;
      $comment_words[$word]['idf'] = $idf;
      $comment_words[$word]['CCi'] = $CCi;
    }
    $res->free();
    // Na podstawie zebranych danych wylicz wagi i znormalizuj warto¶ci dokum.
    $sum = 0.0;
    foreach ($comment as $k => &$v) {
      $v = log($v + 0.5) * $comment_words[$k]['idf'] * $comment_words[$k]['CCi'];
      if ($v == 0.0) {
        $v = log($v + 0.5) * $comment_words[$k]['idf'] * 0.01;
      }
      $sum += $v;
    }
    if ($sum == 0.0) {
      $sum = 1.0;
    }
    $mul = 1/$sum;
    foreach ($comment as $k => &$v) {
      $v *= $mul;
    }
    unset($comment_words);

    // dodaj nowy komentarz
    $sql = "INSERT INTO `datavect_$this->idc-4s` (pn, last_act) VALUES ('$pn',  0)";
    $this->dbconn->query($sql);
    $sql = "INSERT INTO `datavect_$this->idc-4p` (subcat, word, weight) VALUES ";
    $subcat = $this->dbconn->insert_id;
    foreach ($comment as $k => $v) {
      $sql .= "($subcat, '$k', $v),";
    }
    $sql[strlen($sql)-1]=' ';
    $this->dbconn->query($sql);

    // usuñ najdawniej nieu¿ywan± grupê, je¶li liczba grup
    // jest wiêksza od $this->maxComments
    $sql = "SELECT COUNT(subcat) FROM `datavect_$this->idc-4s`";
    $count = DBHelper::getOne($this->dbconn, $sql);
    if ($count <= $this->maxComments) {
      return;
    }
    // wybierz najdawniej nieu¿ywan± grupê
    $sql = "SELECT subcat FROM `datavect_$this->idc-4s` ORDER BY last_act DESC LIMIT 1";
    $subcat = DBHelper::getOne($this->dbconn, $sql);
    // usuñ z³±czane grupy, które wchodz± w sk³ad nowej grupy g³ównej
    $sql = "DELETE `datavect_$this->idc-4s` WHERE subcat = $subcat";
    $this->dbconn->query($sql);
    $sql = "DELETE `datavect_$this->idc-4p` WHERE subcat = $subcat";
    $this->dbconn->query($sql);
  }

  /**
   * Dokonuje wstêpnego wype³nienia zbiorów cech danymi przekazanymi w dwóch
   * tablicach. S± to wyrazy z kilku komentarzy wstêpnych.
   * W normalnej pracy klasyfikatora metoda ta nie jest wykorzystywana!
   *
   * @param array $init_comP Komentarze pozytywne.
   * @param array $init_comN Komentarze negatywne.
   */
  public function doInit(array $init_comP, array $init_comN) {
    // wyczy¶æ co jest...
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-4f`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-4p`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-4s`");
    // ostatnia z tabel zostanie ustawiona nieco pó¼niej na warto¶æ pocz±tkow±
    $prep_arrayP = array();
    $prep_arrayN = array();
    $all_words = array();
    // utwórz z³±czone dane wyrazowe pierwszych komentarzy poz. i neg.
    foreach ($init_comP as $c) {
      $temp = $this->doPreparation($c);
      if ($temp !== false) {
        $prep_arrayP[] = $temp;
        foreach ($temp as $k => $v) {
          ++$all_words[$k][0];
        }
      }
    }
    foreach ($init_comN as $c) {
      $temp = $this->doPreparation($c);
      if ($temp !== false) {
        $prep_arrayN[] = $temp;
        foreach ($temp as $k => $v) {
          ++$all_words[$k][1];
        }
      }
    }
    // zlicz dokumenty pocz±tkowe i uaktualnij tablicê
    $Nkp = count($prep_arrayP);
    $Nkn = count($prep_arrayN);
    $this->dbconn->query("UPDATE `datavect_$this->idc-4o` SET Nkp = $Nkp,  Nkn = $Nkp");
    // uaktualnij dfik
    $sql = "INSERT INTO `datavect_$this->idc-4f` (word, dfikp, dfikn) VALUES (?, ?, ?)";
    $stmt = $this->dbconn->prepare($sql);
    $stmt->bind_param("sii", $k, $tmpp, $tmpn);
    foreach ($all_words as $k => $v) {
      list ($tmpp, $tmpn) = $v;
      if (is_null($tmpp)) {
        $tmpp = 0;
      }
      if (is_null($tmpn)) {
        $tmpn = 0;
      }
      $stmt->execute();
    }
    $stmt->close();
    unset($all_words);
    // uaktualnij pozosta³e zmienne statystyczne dla wszystkich wyrazów
    $logNkp = log($Nkp+1, 2);
    $logNkn = log($Nkn+1, 2);
    $NkSum = $Nkp + $Nkn;
    $sql = "UPDATE `datavect_$this->idc-4f`
    SET WCikp = LOG2(dfikp+1)/$logNkp, WCikn = LOG2(dfikn+1)/$logNkn,
    CCi = 2 * LOG2((2 * GREATEST(dfikp,dfikn)) / (dfikp + dfikn)),
    idf = LOG($NkSum/(dfikp+dfikn));";
    $this->dbconn->query($sql);
    // pobierz wyliczone informacje statystyczne, by przetworzyæ
    $all_words = array();
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-4f`";
    $res = $this->dbconn->query($sql);
    while (($row = $res->fetch_row())) {
      list($word, $CCi, $idf) = $row;
      $all_words[$word]['idf'] = $idf;
      $all_words[$word]['CCi'] = $CCi;
    }
    $res->free();
    // Na podstawie zebranych danych wylicz wagi i znormalizuj warto¶ci dokum.
    // Dodatkowo wstaw komentarz w bazie danych.
    foreach (array('p', 'n') as $pn) {
      $refPrep = 'prep_array'.strtoupper($pn);
      $prep_array = &$$refPrep;
      foreach ($prep_array as &$c) {
        $sql = "INSERT INTO `datavect_$this->idc-4s` (pn, last_act) VALUES ('$pn', 0)";
        $this->dbconn->query($sql);
        $subcat = $this->dbconn->insert_id;
        $sum = 0.0;
        $sql = "INSERT INTO `datavect_$this->idc-4p` (subcat, word, weight) VALUES ";
        foreach ($c as $k => &$v) {
          $v = log($v + 0.5) * $all_words[$k]['idf'] * $all_words[$k]['CCi'];
          if ($v == 0.0) {
            $v = log($v + 0.5) * $comment_words[$k]['idf'] * 0.01;
          }
          $sum += $v;
        }
        if ($sum == 0.0) {
         $sum = 1.0;
        }
        $mul = 1/$sum;
        foreach ($c as $k => &$v) {
          $v *= $mul;
          $sql .= "($subcat, '$k', $v),";
        }
        $sql[strlen($sql)-1]=' ';
        $this->dbconn->query($sql);
      }
    }
  }

}

?>