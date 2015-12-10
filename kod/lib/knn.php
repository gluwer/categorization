<?php
require_once(dirname(__FILE__).'/classify-abs.php');

class Knn extends Classify {

  /**
   * Warto�� najwi�kszej r�nicy podobie�stwa, kt�ra jest dopuszczalna
   * by uzna� grup� za jedn� ca�o��.
   *
   * @var int
   */
  private $k = 10;

  /**
   * Ile maksymalnie komentarzy przechowywa�.
   * Wykorzystywane przez algorytm czyszcz�cy z LRU.
   *
   * @var int
   */
  private $maxComments = 200;

  /**
   * Pr�g. Je�li oceny r�ni� si� o mniej ni� t� warto��,
   * komentarz uznaje si� za nierostrzygni�ty.
   *
   * @var float
   */
  private $threshold = 0.5;

  /**
   * Konstrukor zapewniaj�cy og�ln� inicjalizacj� systemu przygotowywania danych:
   * tokenizer, korekta ortograficzna, uzupe�nianie polskich znak�w, wulgaryzmy.
   *
   * @param mysqli $dbconn Obiekt po��czenia z baz� danych u�ywany w podklasach.
   * @param string $dictdir Folder ze s�ownikami, stoplistami itp.
   * @param int $idc Identyfikator wykorzystywanego zestawu komentarzy.
   * @param bool $copy_unknown Czy pozostawia� nierozpoznane wyrazy?
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
   * Zwraca -1, je�li komentarz nale�y odrzuci� (wg klasyfikacji).
   * Zwraca 1, je�li komentarz nale�y zatwierdzi� (wg klasyfikacji).
   * Zwraca 0, gdy prawdopodobie�stwo pomy�ki jest du�e.
   *
   * @param string $comment Komentarz do sklasyfikowania.
   * @param bool[optional] $prepared Czy nie trzeba przetwarza� komentarza?
   * @return int -1 -> nagatywny, 0 -> nieokre�lony, 1 -> pozytywny
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
   * @param bool[optional] $prepared Czy nie trzeba przetwarza� komentarza?
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
   * @param array $comment Tablica asocjacyjna wyraz�w z wagami.
   * @return array Oceny komentarza (osobno suma negatywnych i pozytywnych).
   */
  protected function evalComment(array $comment) {
    $retContrib = array(0.0, 0.0);
    $model = array();
    // wylicz wagi i sum� ich kwadrat�w
    $comment = $this->evalWid($comment);
    $sumC2 = 0.0;
    foreach ($comment as $v) {
      $sum += $v * $v;
    }

    $similarities = array();
    // wylicz podobie�stwo do ka�dego z komentarzy
    $sql = "SELECT subcat, pn FROM `datavect_$this->idc-4s`";
    $groups = DBHelper::getAssoc($this->dbconn, $sql);
    // przetw�rz ka�d� z grup osobno, je�li podobie�stwo b�dzie wi�ksze
    // od zapami�tanej warto�ci, dodaj miar� podobie�stwa do sumy
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
    // wykorzystaj tylko $this->k najbli�szych komentarzy
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

    // dla grup, kt�re nie bra�y udzia�u w dopasowaniu, zwi�ksz licznik
    // pozosta�ym ustaw licznik na 0
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
   * Wylicza warto�ci wid (wagi poszczeg�lnych cech w dokumencie)
   * na podstawie cz�sto�ci wyst�powania wyraz�w i innych
   * danych zebranych w BD dla wcze�niejszych komentarzy.
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
   * Dokonuje bezpo�redniej aktualizacji zbioru cech.
   *
   * @param array $comment Tablica asocjacyjna (wyraz => liczba wyst�pie�).
   * @param bool $positive Zbi�r cech pozytywnych lub negatywnych?
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
    // aktualizacja istniej�cych dfikp i dodanie nowych wyraz�w
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

    // uaktualnij zmienne statystyczne dla wszystkich wyraz�w
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
    // Na podstawie zebranych danych wylicz wagi i znormalizuj warto�ci dokum.
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

    // usu� najdawniej nieu�ywan� grup�, je�li liczba grup
    // jest wi�ksza od $this->maxComments
    $sql = "SELECT COUNT(subcat) FROM `datavect_$this->idc-4s`";
    $count = DBHelper::getOne($this->dbconn, $sql);
    if ($count <= $this->maxComments) {
      return;
    }
    // wybierz najdawniej nieu�ywan� grup�
    $sql = "SELECT subcat FROM `datavect_$this->idc-4s` ORDER BY last_act DESC LIMIT 1";
    $subcat = DBHelper::getOne($this->dbconn, $sql);
    // usu� z��czane grupy, kt�re wchodz� w sk�ad nowej grupy g��wnej
    $sql = "DELETE `datavect_$this->idc-4s` WHERE subcat = $subcat";
    $this->dbconn->query($sql);
    $sql = "DELETE `datavect_$this->idc-4p` WHERE subcat = $subcat";
    $this->dbconn->query($sql);
  }

  /**
   * Dokonuje wst�pnego wype�nienia zbior�w cech danymi przekazanymi w dw�ch
   * tablicach. S� to wyrazy z kilku komentarzy wst�pnych.
   * W normalnej pracy klasyfikatora metoda ta nie jest wykorzystywana!
   *
   * @param array $init_comP Komentarze pozytywne.
   * @param array $init_comN Komentarze negatywne.
   */
  public function doInit(array $init_comP, array $init_comN) {
    // wyczy�� co jest...
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-4f`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-4p`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-4s`");
    // ostatnia z tabel zostanie ustawiona nieco p�niej na warto�� pocz�tkow�
    $prep_arrayP = array();
    $prep_arrayN = array();
    $all_words = array();
    // utw�rz z��czone dane wyrazowe pierwszych komentarzy poz. i neg.
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
    // zlicz dokumenty pocz�tkowe i uaktualnij tablic�
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
    // uaktualnij pozosta�e zmienne statystyczne dla wszystkich wyraz�w
    $logNkp = log($Nkp+1, 2);
    $logNkn = log($Nkn+1, 2);
    $NkSum = $Nkp + $Nkn;
    $sql = "UPDATE `datavect_$this->idc-4f`
    SET WCikp = LOG2(dfikp+1)/$logNkp, WCikn = LOG2(dfikn+1)/$logNkn,
    CCi = 2 * LOG2((2 * GREATEST(dfikp,dfikn)) / (dfikp + dfikn)),
    idf = LOG($NkSum/(dfikp+dfikn));";
    $this->dbconn->query($sql);
    // pobierz wyliczone informacje statystyczne, by przetworzy�
    $all_words = array();
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-4f`";
    $res = $this->dbconn->query($sql);
    while (($row = $res->fetch_row())) {
      list($word, $CCi, $idf) = $row;
      $all_words[$word]['idf'] = $idf;
      $all_words[$word]['CCi'] = $CCi;
    }
    $res->free();
    // Na podstawie zebranych danych wylicz wagi i znormalizuj warto�ci dokum.
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