<?php
/**
Implementacja w jêzyku PHP klasy do obs³ugi plików s³owników
wyrazowych formatu FSA w wersji 5.
Klasa sprawdza jedynie, czy wyraz znajduje siê w s³owniku.

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
class Fsav {
  /** Indeks pocz±tku w³a¶ciwych danych automatu stanów.*/
  private $start = 0x04;
  /** Dane automatu stanów jako tekst. */
  private $data = "";

  /** Konstruktor.
   *  Wczytuje automat i inicjalizuje tablicê akcentów.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyrazów.
   */
  public function __construct($fsa_file) {
    // za³adowanie i weryfikacja automatu
    $temp = file_get_contents($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s³ownika");
    if (ord($temp[4])!=5) exit("Nieobs³ugiwana wersja s³ownika");
    if (ord($temp[7])!=2) exit("Nieobs³ugiwany rozmiar danych ³uku");
    $this->data = substr($temp,8);
  }

 /** Sprawd¼, czy wyraz znajduje siê w s³owniku.
  *
   *  @param string $word Wyraz do analizy.
   *  @return bool true - je¶li wyraz w s³owniku
   *          false - brak wyrazu w s³owniku
   */
  public function find_word($word) {
    $p = $this->start;
    $p = $this->get_arc($p, "^");
    $p = $this->target_node($p);
    for ($i=0; $i<strlen($word); $i++) {
      $p = $this->get_arc($p,$word{$i});
      if ($p) {
        if ($i==strlen($word)-1 && ((ord($this->data{$p+1}) & 1) == 1)) return true;
        $p = $this->target_node($p);
      } else return false;
    }
    return false;
  }

  // Zwraca adres najbli¿szego, nastêpnego wêz³a w ci±gu znaków.
  private function next_node($pos) {
    $t = $pos;
    while (!((ord($this->data[$t+1]) & 4) == 4)) $t += ((ord($this->data[$t+1]) & 4) == 4)?2:4;
    return ((ord($this->data[$t+1]) & 4) == 4)?$t+1:$t+3;
  }

  // Zwraca indeks wêz³a docelowego dla wskazanego ³uku.
  private function target_node($pos) {
    if ((ord($this->data[$pos+1]) & 4) == 4) {
      return $this->next_node($pos);
    } else {
      $t = 0;
      for ($i=2; $i>0; $i--) {
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
    if ($label == $this->data[$t]) return $t;
    while (!((ord($this->data[$t+1]) & 2) == 2)) {
      $t += ((ord($this->data[$t+1]) & 4) == 4)?1:3;
      if ($label == $this->data[$t]) return $t;
    }
    return null;
  }
}

?>
