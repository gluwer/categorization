<?php
require_once(dirname(__FILE__).'/classify-abs.php');

class Winnow extends Classify {

  /**
   * Wspó³czynnik awansu.
   *
   * @var float
   */
  private $alpha = 1.5;

  /**
   * Wspó³æzynnik degradacji.
   *
   * @var float
   */
  private $beta = 0.75;

  /**
   * Próg. Je¶li oceny ró¿ni± siê o mniej ni¿ tê warto¶æ,
   * komentarz uznaje siê za nierostrzygniêty.
   *
   * @var float
   */
  private $threshold = 0.15;

  /**
   * Usuwanie cech o mniejszej warto¶ci ni¿ zawarto¶æ zmiennej.
   *
   * @var float
   */
  private $irrelevant1 = 0.0005;

  /**
   * Usuwanie cech o wiêkszej warto¶ci ni¿ zawarto¶æ zmiennej.
   *
   * @var float
   */
  private $irrelevant2 = 2;

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
    if ($positive) {
      $p1 = 'p';
      $p2 = 'n';
    } else {
      $p1 = 'n';
      $p2 = 'p';
    }
    $update = 1;
    $this->updateVector($tok_comment,$p1,true);
    $this->updateVector($tok_comment,$p2,false);
    // aktualizacja wielokrotna
/*    do {
      $scoreP = $this->evalComment($tok_comment,'p');
      $scoreN = $this->evalComment($tok_comment,'n');
      if ($positive) $ppp='+++'; else $ppp='---';
      if ($update == 1) echo $ppp.'#'.$scoreP.'/'.$scoreN."->";
      if (($positive && !($scoreP >= $scoreN+$this->threshold)) || (!$positive && ($scoreN >= $scoreP+$this->threshold))) {
        $this->updateVector($tok_comment,$p1,true);
        $this->updateVector($tok_comment,$p2,false);
        --$update;
        //echo "!";
      } else {
        $update=0;
      }
    } while ($update);
    $scoreP = $this->evalComment($tok_comment,'p');
    $scoreN = $this->evalComment($tok_comment,'n');
    echo $scoreP.'/'.$scoreN."\n";*/
  }

  /**
   * Dokonaj wyliczenia oceny dla komentarza ze zbioru cech pozytywnych
   * lub negatywnych.
   *
   * @param array $comment Tablica asocjacyjna wyrazów z wagami.
   * @param string $pn Zbiór cech pozytywnych lub negatywnych?
   * @return float Ocena komentarza wzglêdem wybranego zbioru cech.
   */
  protected function evalComment(array $comment, $pn) {
    $dbcom = "('".implode("','",array_keys($comment))."')";
    $sql = "SELECT word, (wp - wn) AS diff FROM `datavect_$this->idc-1$pn`
    WHERE word IN $dbcom";
    //echo $dbcom."\n";
    $res = $this->dbconn->query($sql);
    $sum = 0.0;
    while (($row = $res->fetch_row())) {
      $sum += sqrt($comment[$row[0]])*$row[1];
    }
    $res->free();
    return $sum;
  }

  /**
   * Dokonuje bezpo¶redniej aktualizacji zbioru cech.
   *
   * @param array $comment Tablica asocjacyjna wyrazów z wagami.
   * @param string $pn Zbiór cech pozytywnych lub negatywnych?
   * @param bool[optional] $positive Wzmoznienie (true) czy os³abienie cech?
   */
  protected function updateVector(array $comment, $pn, $positive = true) {
    $dbcom = "('".implode("','",array_keys($comment))."')";
    if ($positive) {
      $a = $this->alpha;
      $b = $this->beta;
    } else {
      $b = $this->alpha;
      $a = $this->beta;
    }
    $div = 10.01 + count($comment);
    if ($div<10.0) {
      $div = 10.0;
    }
    $ai = $a/$div;
    $bi = $b/$div;
    // aktualizacja istniej±cych i dodanie nowych
    $sql = "INSERT INTO `datavect_$this->idc-1$pn` (word,wp,wn) VALUES ";
    foreach ($comment as $k => $v) {
      $sqrtv = sqrt($v);
      $sql.= "('$k',".($sqrtv*$ai).','.($sqrtv*$bi).'),';
    }
    $sql[strlen($sql)-1]=' ';
    $sql .= "ON DUPLICATE KEY UPDATE wp = wp * $a, wn = wn * $b";
    // usuniêcie skrajnych warto¶ci
    // TODO: zmieniæ sposób usuwania najczêstszych wyrazów??
    $this->dbconn->query($sql);
    $sql = "DELETE FROM `datavect_$this->idc-1$pn` WHERE ABS(wp - wn) < $this->irrelevant1";
    $this->dbconn->query($sql);
    $sql = "DELETE FROM `datavect_$this->idc-1$pn` WHERE ABS(wp - wn) > $this->irrelevant2";
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
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-1p`");
    $this->dbconn->query("TRUNCATE TABLE `datavect_$this->idc-1n`");
    $final_arrayP = array();
    $final_arrayN = array();
    $prep_arrayP = array();
    $prep_arrayN = array();
    // utwórz z³±czone dane wyrazowe pierwszych 10 komentarzy poz. i neg.
    foreach ($init_comP as $c) {
      $temp = $this->doPreparation($c);
      if ($temp !== false) {
        foreach ($temp as $k => $v) {
          $final_arrayP[$k]+=$v;
        }
        $prep_arrayP[] = $temp;
      }
    }
    foreach ($init_comN as $c) {
      $temp = $this->doPreparation($c);
      if ($temp !== false) {
        foreach ($temp as $k => $v) {
          $final_arrayN[$k]+=$v;
        }
        $prep_arrayN[] = $temp;
      }
    }
    // wylicz dla nich pocz±tkowe wagi
    $initPn = 1.0/array_sum($final_arrayP);
    $initNn = 1.0/array_sum($final_arrayN);
    $initPp = $initPn*2;
    $initNp = $initNn*2;
    // dokonaj wstawienia warto¶ci
    $insertP = "INSERT INTO `datavect_$this->idc-1p` (word,wp,wn) VALUES ";
    foreach ($final_arrayP as $k => $v) {
      $insertP.= "('$k',".($v*$initPp).','.($v*$initPn).'),';
    }
    $insertP[strlen($insertP)-1]=';';
    $this->dbconn->query($insertP);
    $insertN = "INSERT INTO `datavect_$this->idc-1n` (word,wp,wn) VALUES ";
    foreach ($final_arrayN as $k => $v) {
      $insertN.= "('$k',".($v*$initNp).','.($v*$initNn).'),';
    }
    $insertN[strlen($insertN)-1]=';';
    $this->dbconn->query($insertN);
    // przetwórz komenatarze, by zmieniæ nieco warto¶ci domy¶lne.
    foreach ($prep_arrayP as $c) {
      $update = 10;
      do {
        $scoreP = $this->evalComment($c,'p');
        $scoreN = $this->evalComment($c,'n');
        if (!($scoreP >= $scoreN+$this->threshold)) {
          $this->updateVector($c,'p',true);
          $this->updateVector($c,'n',false);
          --$update;
        } else {
          $update = 0;
        }
      } while ($update);
    }
    foreach ($prep_arrayN as $c) {
      $update = 10;
      do {
        $scoreP = $this->evalComment($c,'p');
        $scoreN = $this->evalComment($c,'n');
        if (!($scoreN >= $scoreP+$this->threshold)) {
          $this->updateVector($c,'n',true);
          $this->updateVector($c,'p',false);
          --$update;
        } else {
          $update = 0;
        }
      } while ($update);
    }
  }

}

?>