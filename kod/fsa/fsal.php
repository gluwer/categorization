<?PHP
/**
Implementacja w j�zyku PHP klasy do obs�ugi plik�w s�ownik�w odmian
zapisanych jako sko�czone automaty deterministyczne w plikach formatu
FSA w wersji 5.

Autor: Wojciech Rutkowski
  <w.rutkowski at kie.ae.poznan.pl>
Strona projektu:
  http://seneca.kie.ae.poznan.pl/
Modyfikacje klasy na potrzeby pracy magisterskej -
konwersja do PHP5, optymalizacja, dodatkowe komentarze, obs�uga glt = 4:
Rafa� Jo�ca <jeffar at fr.pl>


Referencje:
- Narz�dzia dla automat�w sko�czonych
  Jana Daciuka (implementacja FSA w j�zyku C):
  http://www.eti.pg.gda.pl/katedry/kiw/pracownicy/Jan.Daciuk/personal/fsa_polski.html
- Lametyzator Dawida Weissa
  (implementacja FSA w j�zyku JAVA):
  http://www.cs.put.poznan.pl/dweiss/xml/projects/lametyzator/index.xml?lang=pl
*/
class Fsal {
  /** Indeks pocz�tku w�a�ciwych danych automatu stan�w.*/
  private $start = 0x05;
  /** Dane automatu stan�w jako tekst. */
  private $data = "";
  /** Stosowany separator. */
  private $annot_sep;

  /** Konstruktor.
   *  Wczytuje automat.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyraz�w.
   */
  public function __construct($fsa_file) {
    $temp = file_get_contents($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s�ownika");
    if (ord($temp[4])!=5) exit("Nieobs�ugiwana wersja s�ownika");
    if (ord($temp[7])!=4) exit("Nieobs�ugiwany rozmiar danych �uku");
    $this->annot_sep = $temp{6};
    $this->data = substr($temp,8);
  }

  // Zwraca true, je�li �uk o podanym adresie jest �ukiem ko�cowym.
  private function arc_is_final($pos) {
    $t = ord($this->data[$pos+1]);
    if (($t & 1) == 1) return true;
    else return false;
  }

  // Zwraca true, je�li nast�pnym elementem w ci�gu jest w�ze� docelowy.
  private function target_node_is_next($pos) {
    $t = ord($this->data[$pos+1]);
    if (($t & 4) == 4) return true;
    else return false;
  }

  // Zwraca true, je�li jest to ostatni �uk analizowanego w�z�a.
  private function arc_is_last($pos) {
    $t = ord($this->data[$pos+1]);
    if (($t & 2) == 2) return true;
    else return false;
  }

  // Zwraca indeks nast�pnego �uku.
  private function next_arc($pos) {
    if ($this->target_node_is_next($pos) || $this->arc_is_final($pos))
      return $pos+2;
    else
      return $pos+5;
  }

  // Zwraca adres najbli�szego, nast�pnego w�z�a w ci�gu znak�w.
  private function next_node($pos){
    $t = $pos;
    while (!$this->target_node_is_next($t)) $t = $this->next_arc($t);
    return $this->next_arc($t);
  }

  // Zwraca etykiet� �uku.
  private function arc_label($pos) {
    return $this->data[$pos];
  }

  // Zwraca indeks w�z�a docelowego dla wskazanego �uku.
  private function target_node($pos) {
    if ($this->target_node_is_next($pos)){
      return $this->next_node($pos);
    } elseif ($this->arc_is_final($pos)) {
      return null;
    } else {
      $t = 0;
      for ($i=4; $i>0; $i--) {
        $t <<= 8;
        $t |= (ord($this->data[$pos+$i]) & 0xff);
      }
      $t >>= 3;
      return $t;
    }
  }

  // Poszukuje dla w�z�a �uku o podanej etykiecie.
  private function get_arc($pos, $label) {
    $t = $pos;
    if ($label == $this->arc_label($t)) return $t;
    while (!$this->arc_is_last($t)) {
      $t = $this->next_arc($t);
      if ($label == $this->arc_label($t)) return $t;
    }
    return null;
  }

  // Znajduje wszelkie mo�liwe �cie�ki od danego w�z�a.
  private function traverse($node, $prefix, &$tab) {
    $p = $node;
    if ($this->arc_is_final($p)) $tab[]=$prefix.$this->arc_label($p);
    else $this->traverse($this->target_node($p), $prefix.$this->arc_label($p), $tab);
    while (!$this->arc_is_last($p)) {
      $p = $this->next_arc($p);
      if ($this->arc_is_final($p)) $tab[]=$prefix.$this->arc_label($p);
      else $this->traverse($this->target_node($p), $prefix.$this->arc_label($p), $tab);
    }
  }

  // Sprawdza istnienie wyrazu.
  private function go($word) {
    $p = $this->start;
    $p = $this->get_arc($p, "^");
    $p = $this->target_node($p);
    for ($i=0; $i<strlen($word); $i++) {
      $p = $this->get_arc($p,$word[$i]);
      if ($p) {
        if ($this->arc_is_final($p)) return null;
        else $p = $this->target_node($p);
      }
      else return false;
    }
    return ($p);
  }

  /** Dokonuje sprowadzenia wyrazu do postaci s�ownikowej.
   *
   *  @param string $word Wyraz do przeanalizowania.
   *  @return array Warto�� false, je�li wyrazu nie odnaleziono.
   *          List� s��w s�ownikowych mog�cych odpowiada�
   *          danemu wyrazowi.
   */
  public function lematize($word) {
    // przejd� przez automat wg podanego s�owa
    $p = $this->go($word);
    if ($p) {   // je�eli s�owo by�o w s�owniku
      // przejd� do separatora
      $p = $this->get_arc($p,$this->annot_sep);
      if ($p) {
        $p = $this->target_node($p);
        // znajd� wszystkie mo�liwe �cie�ki
        $tab = array();
        $this->traverse($p,"",$tab);
        $output = array();
        foreach ($tab as $t) {
          // oddzielenie ostatniego cz�onu po separatorze
          // dekompresja lematu
          $l = strpos($t,$this->annot_sep);
          $c = (ord($t{0})-65);
          if ($c>0) $output[] = substr($word, 0, -$c).substr($t,1,$l-1);
          else $output[] = $word.substr($t,1,$l-1);
        }
        return $output;
      }
      else return false;
    }
    else return false;
  }
}

?>
