<?PHP
/**
Implementacja w jêzyku PHP klasy do obs³ugi plików s³owników odmian
zapisanych jako skoñczone automaty deterministyczne w plikach formatu
FSA w wersji 5.

Autor: Wojciech Rutkowski
  <w.rutkowski at kie.ae.poznan.pl>
Strona projektu:
  http://seneca.kie.ae.poznan.pl/
Modyfikacje klasy na potrzeby pracy magisterskej -
konwersja do PHP5, optymalizacja, dodatkowe komentarze, obs³uga glt = 4:
Rafa³ Joñca <jeffar at fr.pl>


Referencje:
- Narzêdzia dla automatów skoñczonych
  Jana Daciuka (implementacja FSA w jêzyku C):
  http://www.eti.pg.gda.pl/katedry/kiw/pracownicy/Jan.Daciuk/personal/fsa_polski.html
- Lametyzator Dawida Weissa
  (implementacja FSA w jêzyku JAVA):
  http://www.cs.put.poznan.pl/dweiss/xml/projects/lametyzator/index.xml?lang=pl
*/
class Fsal {
  /** Indeks pocz±tku w³a¶ciwych danych automatu stanów.*/
  private $start = 0x05;
  /** Dane automatu stanów jako tekst. */
  private $data = "";
  /** Stosowany separator. */
  private $annot_sep;

  /** Konstruktor.
   *  Wczytuje automat.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyrazów.
   */
  public function __construct($fsa_file) {
    $temp = file_get_contents($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s³ownika");
    if (ord($temp[4])!=5) exit("Nieobs³ugiwana wersja s³ownika");
    if (ord($temp[7])!=4) exit("Nieobs³ugiwany rozmiar danych ³uku");
    $this->annot_sep = $temp{6};
    $this->data = substr($temp,8);
  }

  // Zwraca true, je¶li ³uk o podanym adresie jest ³ukiem koñcowym.
  private function arc_is_final($pos) {
    $t = ord($this->data[$pos+1]);
    if (($t & 1) == 1) return true;
    else return false;
  }

  // Zwraca true, je¶li nastêpnym elementem w ci±gu jest wêze³ docelowy.
  private function target_node_is_next($pos) {
    $t = ord($this->data[$pos+1]);
    if (($t & 4) == 4) return true;
    else return false;
  }

  // Zwraca true, je¶li jest to ostatni ³uk analizowanego wêz³a.
  private function arc_is_last($pos) {
    $t = ord($this->data[$pos+1]);
    if (($t & 2) == 2) return true;
    else return false;
  }

  // Zwraca indeks nastêpnego ³uku.
  private function next_arc($pos) {
    if ($this->target_node_is_next($pos) || $this->arc_is_final($pos))
      return $pos+2;
    else
      return $pos+5;
  }

  // Zwraca adres najbli¿szego, nastêpnego wêz³a w ci±gu znaków.
  private function next_node($pos){
    $t = $pos;
    while (!$this->target_node_is_next($t)) $t = $this->next_arc($t);
    return $this->next_arc($t);
  }

  // Zwraca etykietê ³uku.
  private function arc_label($pos) {
    return $this->data[$pos];
  }

  // Zwraca indeks wêz³a docelowego dla wskazanego ³uku.
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

  // Poszukuje dla wêz³a ³uku o podanej etykiecie.
  private function get_arc($pos, $label) {
    $t = $pos;
    if ($label == $this->arc_label($t)) return $t;
    while (!$this->arc_is_last($t)) {
      $t = $this->next_arc($t);
      if ($label == $this->arc_label($t)) return $t;
    }
    return null;
  }

  // Znajduje wszelkie mo¿liwe ¶cie¿ki od danego wêz³a.
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

  /** Dokonuje sprowadzenia wyrazu do postaci s³ownikowej.
   *
   *  @param string $word Wyraz do przeanalizowania.
   *  @return array Warto¶æ false, je¶li wyrazu nie odnaleziono.
   *          Listê s³ów s³ownikowych mog±cych odpowiadaæ
   *          danemu wyrazowi.
   */
  public function lematize($word) {
    // przejd¼ przez automat wg podanego s³owa
    $p = $this->go($word);
    if ($p) {   // je¿eli s³owo by³o w s³owniku
      // przejd¼ do separatora
      $p = $this->get_arc($p,$this->annot_sep);
      if ($p) {
        $p = $this->target_node($p);
        // znajd¼ wszystkie mo¿liwe ¶cie¿ki
        $tab = array();
        $this->traverse($p,"",$tab);
        $output = array();
        foreach ($tab as $t) {
          // oddzielenie ostatniego cz³onu po separatorze
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
