<?php
require_once(dirname(__FILE__).'/classify-abs.php');

class DCMPlus extends Classify {

  /**
   * Próg. Je¶li oceny ró¿ni± siê o mniej ni¿ tê warto¶æ,
   * komentarz uznaje siê za nierostrzygniêty.
   *
   * @var float
   */
  private $threshold = 0.00;

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
    $scoreP = $this->evalComment($tok_comment,'p');
    $scoreN = $this->evalComment($tok_comment,'n');
    //echo "$scoreP -- $scoreN\n";
    if ($scoreP==0 && $scoreN==0) {
      return -1;
    }
    if ($scoreP >= $scoreN+$this->threshold) {
      return 1;
    } else if ($scoreN >= $scoreP+$this->threshold) {
      return -1;
    }
    return 0;
  }

  /**
   * Dokonuje aktualizacji zbioru cech.
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
   * Dokonaj wyliczenia oceny dla komentarza ze zbioru cech pozytywnych
   * lub negatywnych.
   *
   * @param array $comment Tablica asocjacyjna wyrazów z wagami (bez log).
   * @param string $pn Zbiór cech pozytywnych lub negatywnych?
   * @return float Ocena komentarza wzglêdem wybranego zbioru cech.
   */
  protected function evalComment(array $comment, $pn) {
    $sumWikWid = 0.0;
    $sumW2ik = 0.0;
    $sumW2id = 0.0;

    // wyliczenie wag wyrazów z wykorzystaniem log
    $com_log = $this->evalWid($comment);
    // wyliczenie jednego z elementów g³ównego wzoru
    foreach ($com_log as $v) {
    	$sumW2id += $v * $v;
    }

    $dbcom = "('".implode("','",array_keys($comment))."')";
    $sql = "SELECT word, dfik$pn, WCik$pn, CCi, wid$pn FROM `datavect_$this->idc-2`
    WHERE word IN $dbcom";
    $res = $this->dbconn->query($sql);
    while (($row = $res->fetch_row())) {
      list($word, $dfik, $WCik, $CCi, $wid) = $row;
      $temp = floatval($WCik) * $WCik * $CCi * $CCi;
      if ($dfik == 0 || $temp == 0.0) continue; // zabezp. przed dzieleniem przez zero
      $AIik = pow(floatval($wid)/$dfik,2-$WCik);
      $Wik = $AIik * (1.414213 * ($temp/sqrt((floatval($WCik) * $WCik) + ($CCi * $CCi))));

      $sumW2ik += $Wik * $Wik;
      $sumWikWid += $Wik * $com_log[$word];
    }
    $res->free();
    if ($sumWikWid == 0.0) return 0.0;
    $Sdk = $sumWikWid / ($sumW2id + $sumW2ik - $sumWikWid);
    return $Sdk;
  }

  /**
   * Wylicza warto¶ci wid (wagi poszczególnych cech w dokumencie)
   * na podstawie czêsto¶ci wystêpowania wyrazów.
   *
   * @param array $comment
   * @return unknown
   */
  protected function evalWid(array $comment) {
    $ret_array = array();
    // log2 (liczba elementów + 1)
    $log2ld = log(count($comment)+1, 2);
    // wylicz wid
    foreach ($comment as $k => $v) {
      $ret_array[$k] = log($v+1, 2)/$log2ld;
    }
    return $ret_array;
  }

  /**
   * Dokonuje bezpo¶redniej aktualizacji zbioru cech.
   *
   * @param array $comment Tablica asocjacyjna (wyraz => waga po log.).
   * @param bool $positive Zbiór cech pozytywnych lub negatywnych?
   */
  protected function updateVector(array $comment, $positive) {
    if ($positive) {
      $pn = 'p';
    } else {
      $pn = 'n';
    }
    // uaktualnij i pobierz Nk
    $sql = "UPDATE `datavect_$this->idc-2o` SET Nk$pn = Nk$pn+1";
    $this->dbconn->query($sql);
    $sql = "SELECT Nk$pn FROM `datavect_$this->idc-2o`";
    $Nk = DBHelper::getOne($this->dbconn,$sql);
    $logNk = log($Nk+1, 2);
    $oneDivLogNK = 1/$logNk;
    // aktualizacja istniej±cych i dodanie nowych
    $sql = "INSERT INTO `datavect_$this->idc-2` (word,dfikp,dfikn,WCikp,WCikn,CCi,widp,widn) VALUES ";
    $sql.= "(?, ";
    $sql.= $positive?"1, 0, ?, 0":"0, 1, 0, ?";
    $sql.= ", LOG2(dfik$pn), ";
    $sql.= $positive?"?, 0":"0, ?";
    $sql.=") ";
    $sql .= "ON DUPLICATE KEY UPDATE dfik$pn = dfik$pn + 1, wid$pn = wid$pn + ?";
    $stmt = $this->dbconn->prepare($sql);
    $stmt->bind_param("sddd", $k, $oneDivLogNK, $v, $v);
    foreach ($comment as $k => $v) {
      $stmt->execute();
    }
    $stmt->close();
    // Aktualizacja elementów globalnych (dotycz±cych wszystkich wyrazów).
    $sql = "UPDATE `datavect_$this->idc-2` SET WCik$pn = LOG2(dfik$pn+1)/$logNk, CCi = LOG2((2 * GREATEST(dfikp,dfikn)) / (dfikp + dfikn))";
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
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-2`");
    $this->dbconn->query("UPDATE `datavect_$this->idc-2o` SET Nkp = 0,  Nkn = 0");
    // wstawienie pierwszych 10 komentarzy poz. i neg.
    foreach ($init_comP as $c) {
      $temp = $this->doPreparation($c);
      if ($temp !== false) {
        $this->updateVector($this->evalWid($temp),true);
      }
    }
    foreach ($init_comN as $c) {
      $temp = $this->doPreparation($c);
      if ($temp !== false) {
        $this->updateVector($this->evalWid($temp),false);
      }
    }
  }

}

?>