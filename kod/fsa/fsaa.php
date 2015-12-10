<?php
/**
Implementacja w jêzyku PHP klasy do obs³ugi plików s³owników
ortograficznych formatu FSA w wersji 5.
Klasa dokonuje korekty polskich znaków diakrytycznych.

Autor: Rafa³ Joñca <jeffar at fr.pl>
Algorytmy u¿yte w rozwi±zaniu pochodz± z prac Jana Daciuka i Kemala Oflazera.
Dodatkowo sam sposób obs³ugi FSA by³ wzorowany na pracy
Wojciecha Rutkowskiego dla projektu SENECA.

Referencje:
- Narzêdzia dla automatów skoñczonych
  Jana Daciuka (implementacja FSA w jêzyku C):
  http://www.eti.pg.gda.pl/katedry/kiw/pracownicy/Jan.Daciuk/personal/fsa_polski.html
- Projekt SENECA
  (implementacja FSA dla lemów w jêzyku PHP4)
  http://seneca.kie.ae.poznan.pl/
*/
class Fsaa {
  /** Indeks pocz±tku w³a¶ciwych danych automatu stanów.*/
  private $start = 0x04;
  /** Dane automatu stanów jako tekst. */
  private $data = "";
  /** Tablica akcentów, wype³niania w konstruktorze. */
  private $accents = array();
  /** Tablica wyników (znalezionych wyrazów). */
  private $results = array();
  /** Aktualnie analizowany kandydat. */
  private $candidate =" ";
  /** Analizowany wyraz. */
  private $word_ff =" ";

  /** Konstruktor.
   *  Wczytuje automat i inicjalizuje tablicê akcentów.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyrazów.
   */
  public function __construct ($fsa_file) {
    // za³adowanie i weryfikacja automatu
    $temp = file_get_contents ($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s³ownika");
    if (ord($temp[4])!=5) exit("Nieobs³ugiwana wersja s³ownika");
    if (ord($temp[7])!=3) exit("Nieobs³ugiwany rozmiar danych ³uku");
    $this->data = substr($temp,8);

    // inicjalizacja tablicy akcentów
    for ($i = 0; $i < 256; $i++) {
      $this->accents[chr($i)] = chr($i);
    }
    $this->accents['±']='a'; $this->accents['¡']='A';
    $this->accents['æ']='c'; $this->accents['Æ']='C';
    $this->accents['ê']='e'; $this->accents['Ê']='E';
    $this->accents['³']='l'; $this->accents['£']='L';
    $this->accents['ñ']='n'; $this->accents['Ñ']='N';
    $this->accents['ó']='o'; $this->accents['Ó']='O';
    $this->accents['¶']='s'; $this->accents['¦']='S';
    $this->accents['¿']='z'; $this->accents['¯']='Z';
    $this->accents['¼']='z'; $this->accents['¬']='Z';
  }

  /** Dokonuje korekty diakrytycznej przekazanego wyrazu.
   *
   *  @param string $word Wyraz do analizy.
   *  @return array Zwraca tablicê z wyrazami z uzupe³nion± diakrytyk±.
   *  Je¶li brak rozpoznanych wyrazów, zwraca false.
  */
  public function accent_word($word) {
    // resetuj tablicê wyników
    $this->results = array();
    $this->candidate = ' ';
    $this->word_ff = $word;
    $this->find_accents(0, 0, $this->start+2);
    // dokonaj posortowania zast±pieñ, je¶li s±, zwróæ wynik
    if (count($this->results)) {
      $this->results = array_unique($this->results);
      sort($this->results,SORT_LOCALE_STRING);
      return $this->results;
    }
    return false;
  }

  // Rekurencyjnie szuka wyrazów z odpowiednimi akcentami.
  private function find_accents($index, $level, $start) {
    $next_node = $start;

    for ($nlast = 1; $nlast; $nlast= !($this->arc_is_last($next_node)), $next_node = $this->next_arc($next_node)) {
      $ch = $this->arc_label($next_node);
      if ($this->word_ff[$index] == $this->accents[$ch] || $this->word_ff[$index] == $ch) {
        $this->candidate[$level] = $ch;
        if (($index+1) == strlen($this->word_ff) && $this->arc_is_final($next_node)) {
	        $this->results[]=$this->candidate;
        } else {
	        $nxt_node =$this->target_node($next_node);
	        $this->find_accents($index + 1, $level + 1, $nxt_node);
        }
      }
    }
  }

  // Zwraca true, je¶li ³uk o podanym adresie jest ³ukiem koñcowym.
  private function arc_is_final($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 1) == 1) return true;
    else return false;
  }

  // Zwraca true, je¶li nastêpnym elementem w ci±gu jest wêze³ docelowy.
  private function target_node_is_next($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 4) == 4) return true;
    else return false;
  }

  // Zwraca true, je¶li jest to ostatni ³uk analizowanego wêz³a.
  private function arc_is_last($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 2) == 2) return true;
    else return false;
  }

  // Zwraca indeks nastêpnego ³uku.
  private function next_arc($pos) {
    if ($this->target_node_is_next($pos))
      return $pos+2;
    else
      return $pos+4;
  }

  // Zwraca adres najbli¿szego, nastêpnego wêz³a w ci±gu znaków.
  private function next_node($pos) {
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
    if ($this->target_node_is_next($pos)) {
      return $this->next_node($pos);
    } else {
      $t = 0;
      for ($i=3; $i>0; $i--) {
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
}

?>
