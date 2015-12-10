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
  (implementacja FSA dla lematów w jêzyku PHP4)
  http://seneca.kie.ae.poznan.pl/
*/
class Fsaa {
  /** Indeks pocz±tku w³a¶ciwych danych automatu stanów. */
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
   *          Je¶li brak rozpoznanych wyrazów, zwraca false.
   */
  public function accent_word($word) {
    // resetuj tablicê wyników
    $this->results = array();
    $this->candidate=' ';
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

    for ($nlast = 1; $nlast; $nlast= !((ord($this->data{$next_node+1}) & 2) == 2), $next_node += ((ord($this->data[$next_node+1]) & 4) == 4)?2:4) {
      $ch = $this->data[$next_node];
      if ($this->word_ff[$index] == $this->accents[$ch] || $this->word_ff[$index] == $ch) {
        $this->candidate[$level] = $ch;
        if (($index+1) == strlen($this->word_ff) && ((ord($this->data[$next_node+1]) & 1) == 1)) {
	        $this->results[]=$this->candidate;
        } else {
          if ((ord($this->data{$next_node+1}) & 4) == 4) {
            $nxt_node = $next_node;
            while (!((ord($this->data[$nxt_node+1]) & 4) == 4)) $nxt_node += ((ord($this->data[$nxt_node+1]) & 4) == 4)?2:4;
            if ((ord($this->data[$nxt_node+1]) & 4) == 4)
              $nxt_node+=2;
            else
              $nxt_node+=4;
          } else {
            $t = 0;
            for ($i=3; $i>0; $i--) {
              $t <<= 8;
              $t |= (ord($this->data[$next_node+$i]) & 0xff);
            }
            $t >>= 3;
            $nxt_node = $t;
          }
	        $this->find_accents($index + 1, $level + 1, $nxt_node);
        }
      }
    }
  }
}

?>