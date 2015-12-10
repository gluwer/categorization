<?php
require_once(dirname(__FILE__).'/classify-abs.php');

class Icnn extends Classify {

  /**
   * Warto¶æ najmniejszego podobieñstwa, która jest dopuszczalna
   * by uznaæ grupê za jedn± ca³o¶æ.
   *
   * @var float
   */
  private $similarity = 0.05;

  /**
   * Próg. Je¶li oceny ró¿ni± siê o mniej ni¿ tê warto¶æ,
   * komentarz uznaje siê za nierostrzygniêty.
   *
   * @var float
   */
  private $threshold = 0.2;

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
    if ($scoreP==0 && $scoreN==0) {
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
    // wylicz globalne podobieñstwo wzglêdem ca³o¶ci pozytywnych i negatywnych
    $dbcom = "('".implode("','",array_keys($comment))."')";
    $sql = "SELECT word, IF(dfikp<>0,sump/dfikp,0.0) AS wp, IF(dfikn<>0,sumn/dfikn,0.0)
    AS wn FROM `datavect_$this->idc-3f` WHERE word IN $dbcom";
    $res = $this->dbconn->query($sql);
    $sumP2 = 0.0;
    $sumN2 = 0.0;
    $sumCP = 0.0;
    $sumCN = 0.0;
    while (($row = $res->fetch_row())) {
      if (array_key_exists($row[0], $comment)) {
        if ($row[1] != 0.0) {
          $sumCP += ($row[1] * $comment[$k]);
        }
        if ($row[2] != 0.0) {
          $sumCN += ($row[2] * $comment[$k]);
        }
      }
      $sumP2 += $row[1] * $row[1];
      $sumN2 += $row[2] * $row[2];
    }
    $res->free();
    if ($sumCP != 0.0) {
      $retContrib[0] += $sumCP / ($sumC2 + $sumP2 - $sumCP);
    }
    if ($sumCN != 0.0) {
      $retContrib[1] += $sumCN / ($sumC2 + $sumN2 - $sumCN);
    }

    $contributingGroups = array();
    // pobierz dane dotycz±ce grup do tablicy
    $sql = "SELECT subcat, pn, sim, num FROM `datavect_$this->idc-3s`";
    $groups = DBHelper::getAll($this->dbconn, $sql);
    // przetwórz ka¿d± z grup osobno, je¶li podobieñstwo bêdzie wiêksze
    // od zapamiêtanej warto¶ci, dodaj miarê podobieñstwa do sumy
    $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = ?";
    $stmt = $this->dbconn->prepare($sql);
    foreach ($groups as &$g) {
    	list($subcat, $pn, $sim, $num) = $g;
    	$stmt->bind_param("i", $subcat);
    	$stmt->execute();
    	$stmt->bind_result($word, $sum);
    	$sumCG = 0.0;
    	$sumG2 = 0.0;
    	while ($stmt->fetch()) {
    	  $weight = $sum/$num;
    	  if (array_key_exists($word, $comment)) {
    	    if ($weight != 0.0) {
    	      $sumCG += ($weight * $comment[$word]);
    	    }
    	  }
    	  $sumG2 += $weight * $weight;
    	}
      if (($sumC2 + $sumG2 - $sumCG) != 0.0) {
        $similarity = $sumCG / ($sumC2 + $sumG2 - $sumCG);
        if ($similarity > $sim) {
          $retContrib[(($pn=='p')?0:1)] += $similarity * log($num + 1, 2);
          $contributingGroups[] = $subcat;
        }
      }
    }
    $stmt->close();
    // dla grup, które nie bra³y udzia³u w dopasowaniu, zwiêksz licznik
    // pozosta³ym ustaw licznik na 0
    $dbCG = "(".implode(",",$contributingGroups).")";
    $sql = "UPDATE `datavect_$this->idc-3s` SET last_act = last_act + 1
    WHERE subcat NOT IN $dbCG";
    $res = $this->dbconn->query($sql);
    $sql = "UPDATE `datavect_$this->idc-3s` SET last_act = 0
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
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-3f` WHERE word IN $dbcom";
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
    $sql = "UPDATE `datavect_$this->idc-3o` SET Nk$pn = Nk$pn+1";
    $this->dbconn->query($sql);
    $sql = "SELECT Nkp, Nkn FROM `datavect_$this->idc-3o`";
    list($Nkp, $Nkn) = DBHelper::getRow($this->dbconn, $sql);
    if ($positive) {
      $logNk = log($Nkp+1, 2);
    } else {
      $logNk = log($Nkn+1, 2);
    }
    $NkSum = $Nkp + $Nkn;
    $oneDivLogNK = 1/$logNk;
    // aktualizacja istniej±cych dfikp i dodanie nowych wyrazów
    $sql = "INSERT INTO `datavect_$this->idc-3f` (word,dfikp,dfikn,WCikp,WCikn,CCi,idf) VALUES ";
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
    $sql = "UPDATE `datavect_$this->idc-3f`
    SET WCik$pn = LOG2(dfik$pn+1)/$logNk,
    CCi = 2 * LOG2((2 * GREATEST(dfikp,dfikn)) / (dfikp + dfikn)),
    idf = LOG($NkSum/(dfikp+dfikn));";
    $this->dbconn->query($sql);
    // pobierz wyliczone informacje statystyczne dla dodawanego komentarza
    $comment_words = array();
    $dbcom = "('".implode("','",array_keys($comment))."')";
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-3f` WHERE word IN $dbcom";
    $res = $this->dbconn->query($sql);
    while (($row = $res->fetch_row())) {
      list($word, $CCi, $idf) = $row;
      $comment_words[$word]['idf'] = $idf;
      $comment_words[$word]['CCi'] = $CCi;
    }
    $res->free();
    // Na podstawie zebranych danych wylicz wagi i znormalizuj warto¶ci dokum.
    // Dodatkowo uaktualnij g³ówne warto¶ci sum w bazie danych.
    $sql = "UPDATE `datavect_$this->idc-3f` SET sum$pn = sum$pn + ? WHERE word = ?";
    $stmt = $this->dbconn->prepare($sql);
    $sum = 0.0;
    $sumC2 = 0.0;
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
      $sumC2 += $v * $v;
      $stmt->bind_param("ds", $v, $k);
      $stmt->execute();
    }
    $stmt->close();
    unset($comment_words);

    // wylicz miary podobieñstwa nowego dokumentu do grup pozytywnych lub negatywnych
    $similarGroups = array();
    // pobierz dane dotycz±ce grup wybranego typu do tablicy
    $sql = "SELECT subcat, sim, num FROM `datavect_$this->idc-3s` WHERE pn = '$pn'";
    $groups = DBHelper::getAll($this->dbconn, $sql);

    // przetwórz ka¿d± z grup osobno, je¶li podobieñstwo bêdzie wiêksze
    // od zapamiêtanej warto¶ci, dodaj miarê podobieñstwa do sumy
    $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = ?";
    $stmt = $this->dbconn->prepare($sql);
    foreach ($groups as &$g) {
    	list($subcat, $sim, $num) = $g;
    	$stmt->bind_param("i", $subcat);
    	$stmt->execute();
    	$stmt->bind_result($word, $sum);
    	$sumCG = 0.0;
    	$sumG2 = 0.0;
    	while ($stmt->fetch()) {
    	  $weight = $sum/$num;
    	  if (array_key_exists($word, $comment)) {
    	    if ($weight != 0.0) {
    	      $sumCG += ($weight * $comment[$word]);
    	    }
    	  }
    	  $sumG2 += $weight * $weight;
    	}
      if ($sumCG != 0.0) {
        $similarity = $sumCG / ($sumC2 + $sumG2 - $sumCG);
        if ($similarity > $sim) {
          $similarGroups[(string)$subcat]  = $similarity;
        }
      }
    }
    $stmt->close();
    $simCounter = count($similarGroups);

    // je¶li $similarGroups jest puste, dodaj now± grupê z jednym komentarzem
    if (!$simCounter) {
      $sql = "INSERT INTO `datavect_$this->idc-3s` (pn, sim, num, last_act) VALUES ('$pn', {$this->similarity}, 1, 0)";
      $this->dbconn->query($sql);
      $sql = "INSERT INTO `datavect_$this->idc-3p` (subcat, word, `sum`) VALUES (".$this->dbconn->insert_id.", ?, ?)";
      $stmt = $this->dbconn->prepare($sql);
      $stmt->bind_param("sd", $k, $vv);
      foreach ($comment as $k => $vv) {
        $stmt->execute();
      }
      $stmt->close();
      // usuñ najdawniej nieu¿ywan± grupê, je¶li dodano now± grupê i liczba grup
      // jest wiêksza od 1/$this->similarity
      // sprawd¼, ile jest grup dla danego typu
      $sql = "SELECT COUNT(subcat) FROM `datavect_$this->idc-3s` WHERE pn = '$pn'";
      $count = DBHelper::getOne($this->dbconn, $sql);
      if ($count < min(max($this->similarity * 5000, 50), 500)) {
        return;
      }
      // wybierz najdawniej nieu¿ywan± grupê
      $sql = "SELECT subcat FROM `datavect_$this->idc-3s` WHERE pn = '$pn' ORDER BY last_act DESC, num ASC LIMIT 1";
      $subcat = DBHelper::getOne($this->dbconn, $sql);
      // usuñ z³±czane grupy, które wchodz± w sk³ad nowej grupy g³ównej
      $sql = "DELETE `datavect_$this->idc-3s` WHERE subcat = $subcat";
      $this->dbconn->query($sql);
      $sql = "DELETE `datavect_$this->idc-3p` WHERE subcat = $subcat";
      $this->dbconn->query($sql);
      return;
    }
    // znajd¼ najbli¿sze grupy, których podobieñstwo jest wiêksze od $this->similarity
    $addingTo = -1;
    if ($simCounter > 1) {
      arsort($similarGroups);
      $joinGroups = array();
      foreach ($similarGroups as $k => $v) {
      	if ($v > $this->similarity) {
      	  $joinGroups[] = (int) $k;
      	}
      }
      if (count($joinGroups)) {
        $addingTo = array_shift($joinGroups);
      } else {
        list($addingTo) = array_keys($similarGroups);
      }
    } else {
      list($addingTo) = array_keys($similarGroups);
    }
    // je¶li podobieñstwo tylko do jednej grupy lub do kilku grup,
    // ale dwie najbli¿sze grupy nie s± wystarczaj±co blisko siebie
    if (!isset($joinGroups) || count($joinGroups) == 0) {
      // pobierz dane dotycz±ce aktualizowanej grupy
      $sql = "SELECT sim, num FROM `datavect_$this->idc-3s` WHERE subcat = $addingTo";
      list($simOld, $numOld) = DBHelper::getRow($this->dbconn, $sql);
      // pobierz stare dane
      $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = $addingTo";
      $res = $this->dbconn->query($sql);
      $oldGroup = array();
      $sumO2 = 0.0;
      while ($row = $res->fetch_row()) {
        $val = $row[1]/$numOld;
        $oldGroup[$row[0]] = $val;
        $sumO2 += $val;
      }
      $res->close();
      // uaktualnij dane centroidalne wskazanej grupy
      $sql = "INSERT INTO `datavect_$this->idc-3p` (subcat, word, `sum`) VALUES
      ($addingTo, ?, ?) ON DUPLICATE KEY UPDATE sum = sum + ?";
      $stmt = $this->dbconn->prepare($sql);
      $stmt->bind_param("sdd", $k, $v, $v);
      foreach ($comment as $k => $v) {
        $stmt->execute();
      }
      $stmt->close();
      // wylicz podobieñstwo po aktualizacji dla starej grupy i dodanego komentarza
      $num = $numOld + 1;
      $sumG2 = 0.0;
      $sumCG = 0.0;
      $sumOG = 0.0;
      $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = $addingTo";
      $res = $this->dbconn->query($sql);
      while ($row = $res->fetch_row()) {
        list($word, $sum) = $row;
    	  $weight = $sum/$num;
    	  if (array_key_exists($word, $comment)) {
    	    if ($weight != 0.0) {
    	      $sumCG += ($weight * $comment[$word]);
    	    }
    	  }
    	  if (array_key_exists($word, $oldGroup)) {
    	    if ($weight != 0.0) {
    	      $sumOG += ($weight * $oldGroup[$word]);
    	    }
    	  }
    	  $sumG2 += $weight * $weight;
    	}
    	$res->close();
      $similarityC = $sumCG / ($sumC2 + $sumG2 - $sumCG);
      $similarityO = $sumOG / ($sumO2 + $sumG2 - $sumOG);
      // uaktualnij podobieñstwo i inne dane
      $sim = min($simOld, $similarityC, $similarityO);
      $sql = "UPDATE `datavect_$this->idc-3s` SET last_act = 0, num = num + 1,
      sim = $sim WHERE subcat = $addingTo";
      $this->dbconn->query($sql);
    } else {
      // je¶li co najmniej dwie grupy i n pierwszych jest dostatecznie blisko siebie
      // dokonaj ich z³±czenia

      // pobierz dane dotycz±ce aktualizowanej grupy
      $sql = "SELECT sim, num FROM `datavect_$this->idc-3s` WHERE subcat = '$addingTo'";
      list($simOld, $numOld) = DBHelper::getRow($this->dbconn, $sql);
      // pobierz dane dotycz±ce z³±czanych grup
      $joinJoined = '('.implode(',',$joinGroups).')';
      $sql = "SELECT subcat, sim, num FROM `datavect_$this->idc-3s` WHERE subcat IN $joinJoined";
      $joinOldS = DBHelper::getAssoc($this->dbconn, $sql);

      // pobierz stare dane aktualizowanej grupy
      $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = $addingTo";
      $res = $this->dbconn->query($sql);
      $oldGroup = array();
      $sumO2 = 0.0;
      while ($row = $res->fetch_row()) {
        $val = $row[1]/$numOld;
        $oldGroup[$row[0]] = $val;
        $sumO2 += $val;
      }
      $res->close();
      // pobierz stare dane z³±czanych grup, przy okazji wylicz kilka dodatkowych informacji
      $actArray = array();
      $joinOldP = array();
      $sumJ2 = array();
      $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = ?";
      $stmt = $this->dbconn->prepare($sql);
      foreach ($joinGroups as $subcat) {
        $num = $joinOldS[$subcat][1];
        $stmt->bind_param("i", $subcat);
        $stmt->execute();
        $stmt->bind_result($word, $sum);
        $sumJ2[$subcat] = 0.0;
        $joinOldP[$subcat] = array();
        while ($stmt->fetch()) {
          $val = $sum/$num;
          $joinOldP[$subcat][$word] = $val;
          $sumJ2[$subcat] += $val;
          $actArray[$word] += $sum;
        }
      }
      $stmt->close();

      // uaktualnij dane centroidalne wskazanej grupy
      $sql = "INSERT INTO `datavect_$this->idc-3p` (subcat, word, `sum`) VALUES
      ($addingTo, ?, ?) ON DUPLICATE KEY UPDATE sum = sum + ?";
      $stmt = $this->dbconn->prepare($sql);
      $stmt->bind_param("sdd", $k, $v, $v);
      foreach ($comment as $k => $v) {
        $stmt->execute();
      }
      $stmt->bind_param("sdd", $k, $v, $v);
      foreach ($actArray as $k => $v) {
        $stmt->execute();
      }
      $stmt->close();

      // usuñ z³±czane grupy, które wchodz± w sk³ad nowej grupy g³ównej
      $sql = "DELETE `datavect_$this->idc-3s` WHERE subcat IN $joinJoined";
      $this->dbconn->query($sql);
      $sql = "DELETE `datavect_$this->idc-3p` WHERE subcat IN $joinJoined";
      $this->dbconn->query($sql);

      // wylicz podobieñstwo po aktualizacji dla starej grupy, dodanego komentarza
      // i wszystkich pozosta³ych z³±czanych grup
      $num = $numOld + 1;
      foreach ($joinOldS as &$v) {
      	$num += $v[1];
      }
      $sumG2 = 0.0;
      $sumCG = 0.0;
      $sumOG = 0.0;
      $sumOJ = array();
      $sql = "SELECT word, sum FROM `datavect_$this->idc-3p` WHERE subcat = $addingTo";
      $res = $this->dbconn->query($sql);
      while ($row = $res->fetch_row()) {
        list($word, $sum) = $row;
    	  $weight = $sum/$num;
    	  if (array_key_exists($word, $comment)) {
    	    if ($weight != 0.0) {
    	      $sumCG += ($weight * $comment[$word]);
    	    }
    	  }
    	  if (array_key_exists($word, $oldGroup)) {
    	    if ($weight != 0.0) {
    	      $sumOG += ($weight * $oldGroup[$word]);
    	    }
    	  }
        foreach ($joinGroups as $v) {
    	    if (array_key_exists($word, $joinOldP[$v])) {
    	      if ($weight != 0.0) {
    	        $sumOJ[$v] += ($weight * $joinOldP[$v][$word]);
    	      }
    	    }
        }
    	  $sumG2 += $weight * $weight;
    	}
    	$res->close();
      $similarityC = $sumCG / ($sumC2 + $sumG2 - $sumCG);
      $similarityO = $sumOG / ($sumO2 + $sumG2 - $sumOG);
      // uaktualnij podobieñstwo i inne dane
      $sim = min($simOld, $similarityC, $similarityO);
      foreach ($sumOJ as $v) {
      	if ($sim > $v) {
      	  $sim = $v;
      	}
      }
      $sql = "UPDATE `datavect_$this->idc-3s` SET last_act = 0, num = $num,
      sim = $sim WHERE subcat = $addingTo";
      $this->dbconn->query($sql);
    }
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
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-3f`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-3p`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-3s`");
    // ostatnia z tabel zostanie ustawiona nieco pó¼niej na warto¶æ pocz±tkow±
    $prep_arrayP = array();
    $prep_arrayN = array();
    $all_words = array();
    // utwórz z³±czone dane wyrazowe pierwszych 10 komentarzy poz. i neg.
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
    $this->dbconn->query("UPDATE `datavect_$this->idc-3o` SET Nkp = $Nkp,  Nkn = $Nkp");
    // uaktualnij dfik
    $sql = "INSERT INTO `datavect_$this->idc-3f` (word, dfikp, dfikn) VALUES (?, ?, ?)";
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
    $sql = "UPDATE `datavect_$this->idc-3f`
    SET WCikp = LOG2(dfikp+1)/$logNkp, WCikn = LOG2(dfikn+1)/$logNkn,
    CCi = 2 * LOG2((2 * GREATEST(dfikp,dfikn)) / (dfikp + dfikn)),
    idf = LOG($NkSum/(dfikp+dfikn));";
    $this->dbconn->query($sql);
    // pobierz wyliczone informacje statystyczne, by przetworzyæ
    $all_words = array();
    $sql = "SELECT word, CCi, idf FROM `datavect_$this->idc-3f`";
    $res = $this->dbconn->query($sql);
    while (($row = $res->fetch_row())) {
      list($word, $CCi, $idf) = $row;
      $all_words[$word]['idf'] = $idf;
      $all_words[$word]['CCi'] = $CCi;
    }
    $res->free();
    // Na podstawie zebranych danych wylicz wagi i znormalizuj warto¶ci dokum.
    // Dodatkowo uaktualnij g³ówne warto¶ci sum w bazie danych.
    $sql = "UPDATE `datavect_$this->idc-3f` SET sump = sump + ? WHERE word = ?";
    $stmt = $this->dbconn->prepare($sql);
    foreach ($prep_arrayP as &$c) {
      $sum = 0.0;
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
        $stmt->bind_param("ds", $v, $k);
        $stmt->execute();
      }
    }
    $stmt->close();
    $sql = "UPDATE `datavect_$this->idc-3f` SET sumn = sumn + ? WHERE word = ?";
    $stmt = $this->dbconn->prepare($sql);
    foreach ($prep_arrayN as &$c) {
      $sum = 0.0;
      foreach ($c as $k => &$v) {
        $v = log($v + 0.5) * $all_words[$k]['idf'] * $all_words[$k]['CCi'];
        $sum += $v;
      }
      if ($sum == 0.0) {
        $sum = 1.0;
      }
      $mul = 1/$sum;
      foreach ($c as $k => &$v) {
        $v *= $mul;
        $stmt->bind_param("ds", $v, $k);
        $stmt->execute();
      }
    }
    $stmt->close();
    unset($all_words);
    // ten sam sposób przetwarzania dla komentarzy pozytywnych i negatywnych
    foreach (array('p', 'n') as $pn) {
      // znajd¼ lokalne s±siedztwo poszczególnych
      // dokumentów i zapamiêtaj numery s±siadów
      $refPrep = 'prep_array'.strtoupper($pn);
      $prep_array = &$$refPrep;
      $counter = count($prep_array);
      $goodLocals = array();
      $bufferedSums = array();
      for ($i = 0; $i < $counter; ++$i) {
        $sum = 0.0;
        foreach ($prep_array[$i] as $v) {
          $sum += $v * $v;
        }
        $bufferedSums[$i] = $sum;
      }
      for ($i = 0; $i < $counter-1; ++$i) {
        if (!isset($goodLocals[(string)$i])) {
          $goodLocals[(string)$i] = array();
        }
        for ($j = $i+1; $j < $counter; ++$j) {
          $sumIJ = 0.0;
          foreach ($prep_array[$i] as $k => $v) {
            if (array_key_exists($k, $prep_array[$j])) {
              $sumIJ += ($v * $prep_array[$j][$k]);
            }
          }
          if ($sumIJ != 0.0) {
            $tmp = $sumIJ / ($bufferedSums[$i] + $bufferedSums[$j] - $sumIJ);
          } else {
            $tmp = 0.0;
          }
          if ($tmp > $this->similarity) {
            $goodLocals[(string)$i][] = $j;
            $goodLocals[(string)$j][] = $i;
          }
        }
      }
      if (!isset($goodLocals[(string)($counter - 1)])) {
        $goodLocals[(string)($counter - 1)] = array();
      }
      // posortuj dokumenty wzglêdem liczby s±siadów
      uasort($goodLocals, array($this, 'sort1'));
      while (count($goodLocals)) {
        reset($goodLocals);
        // dokument z najwiêksz± liczb± s±siadów ($k) i
        // jego s±siedzi (tablica $v)
        list($key, $localGroup) = each($goodLocals);
        $localGroup[] = (int)$key;
        $groupCard = count($localGroup);
        if ($groupCard == 1) {
          break;
        }
        $centroid = array(); // wyraz -> suma
        // uwzglêdniaj±c tylko te dokumenty z $v, wylicz centroid (zsumuj)
        foreach ($localGroup as $vv) {
          foreach ($prep_array[$vv] as $kk => $vvv) {
            $centroid[$kk] += $vvv;
          }
        }
        // kopiuj tablice i wylicz rzeczywiste wagi dla centroidu kilka wierszy ni¿ek
        $centroid2 = $centroid;
        // wylicz podobieñstwo centroidu do grupy komentarzy
        // (odnajd¼ najgorsze podobieñstwo)
        $centroidSum = 0.0;
        foreach ($centroid2 as &$v) {
          $v /= $groupCard;
          $centroidSum += $v * $v;
        }
        $groupSim = 1.0;
        foreach ($localGroup as $id) {
          $sumIJ = 0.0;
          foreach ($centroid2 as $k => $v) {
            if (array_key_exists($k, $prep_array[$id])) {
              $sumIJ += ($v * $prep_array[$id][$k]);
            }
          }
          if ($sumIJ != 0.0) {
            $tmp = $sumIJ / ($centroidSum + $bufferedSums[$id] - $sumIJ);
          } else {
            $tmp = 0.0;
          }
          if ($groupSim > $tmp) {
            $groupSim = $tmp;
          }
        }
        // zapisz nowy centroid w bazie danych
        $sql = "INSERT INTO `datavect_$this->idc-3s` (pn, sim, num, last_act) VALUES ('$pn', $groupSim, $groupCard, 0)";
        $this->dbconn->query($sql);
        $sql = "INSERT INTO `datavect_$this->idc-3p` (subcat, word, `sum`) VALUES (".$this->dbconn->insert_id.", ?, ?)";
        $stmt = $this->dbconn->prepare($sql);
        $stmt->bind_param("sd", $k, $vv);
        foreach ($centroid as $k => $vv) {
          $stmt->execute();
        }
        $stmt->close();
        // usuñ z $goodLocals wszelkie informacje na temat komentarzy przetworzonych
        // w³a¶nie na centroid
        $tmp = array_keys($goodLocals);
        for ($i = 0; $i < count($tmp); ++$i) {
          if (in_array($tmp[$i], $localGroup)) {
            unset($goodLocals[$tmp[$i]]);
            continue;
          }
          $goodLocals[$tmp[$i]] = array_diff($goodLocals[$tmp[$i]], $localGroup);
        }
        // ponownie przesortuj
        uasort($goodLocals, array($this, 'sort1'));
        // je¶li najlepszy z pozosta³ych komentarzy ma 0 s±siadów, zakoñcz pêtlê
        reset($goodLocals);
        list(, $v) = each($goodLocals);
        if (count($v) == 0) {
          break;
        }
      }
      // dokonaj wpisania do bazy pozosta³ych komentarzy, które nie zosta³y zgrupowane
      $ungroupedKeys = array_keys($goodLocals);
      foreach ($ungroupedKeys as $v) {
        $sql = "INSERT INTO `datavect_$this->idc-3s` (pn, sim, num, last_act) VALUES ('$pn', {$this->similarity}, 1, 0)";
        $this->dbconn->query($sql);
        $sql = "INSERT INTO `datavect_$this->idc-3p` (subcat, word, `sum`) VALUES (".$this->dbconn->insert_id.", ?, ?)";
        $stmt = $this->dbconn->prepare($sql);
        $stmt->bind_param("sd", $k, $vv);
        foreach ($prep_array[(int)$v] as $k => $vv) {
          $stmt->execute();
        }
        $stmt->close();
      }
    }
  }

  // w³asna funkcja zwi±zana z sortowaniem
  private function sort1($a, $b) {
    $ac = count($a);
    $bc = count($b);
    if ($ac == $bc) {
      return 0;
    }
    return ($ac < $bc) ? 1 : -1;
  }

}

?>