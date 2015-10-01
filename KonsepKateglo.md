# Konsep #
Informasi di kateglo sebenarnya semuanya diawali dari "kata". Disini kita kasih nama "[Lemma](http://id.wikipedia.org/wiki/Lemma_%28linguistik%29)". seperti pada umumnya kamus, baik itu kamus definisi ataupun kamus terjemahan.

Dari Lemma kita baru bisa melihat informasi lain yang mendefinisikan Lemma ini.

Sebuah Lemma memiliki:
  * [Syllabel](KonsepKateglo#Syllabel.md)
  * [Type](KonsepKateglo#Type.md)
  * [Definisi](KonsepKateglo#Definisi.md)
  * [Relasi](KonsepKateglo#Relasi.md)
  * [Glossary](KonsepKateglo#Glossary.md)
  * [Etymology](KonsepKateglo#Etymology.md)

## Syllabel ##
Sebuah lemma wajib memiliki [Syllabel](http://en.wikipedia.org/wiki/Syllable) untuk saat ini belum semua lemma memiliki Syllabel.

## Type ##
Sebuah lemma wajib memiliki minimal satu Type.

Jenis Type antara lain:
  * Kata dasar (root)
  * Imbuhan (fixation)
  * Kata turunan (descendant)
  * Peribahasa (proverb)

## Definisi ##
Sebuah lemma wajib memiliki minimal satu definisi. Definisi memiliki informasi tambahan, antara lain :
  * [Lexical](KonsepKateglo#Lexical.md)
  * [Disiplin](KonsepKateglo#Disiplin.md)
  * [Sumber](KonsepKateglo#Sumber.md)

### Lexical ###
Sebuah Definisi memiliki satu [lexical](http://en.wikipedia.org/wiki/Lexical).

Jenis Lexical antara lain:
  * Nomina (kata benda) / noun
  * Verba (kata kerja) / verb
  * Adjektiva (kata sifat) / adjective
  * Adverbia (kata keterangan) / adverb
  * Pronomina (kata ganti) / pronoun
  * Numeralia (kata bilangan) / numeric
  * Lain-lain (preposisi, artikula, dll) / other

### Disiplin ###
Sebuah Definisi memiliki satu disiplin.

Jenis Disiplin sebagai contoh:
  * Biologi
  * Ekonomi
  * Fisika
  * Kimia
  * Linguistik
  * Matematika
  * Olahraga
  * Pariwisata
  * Politik

### Sumber ###
Sebuah Definisi dapat diambil dari beberapa sumber. Sebuah sumber didefinisikan dengan URL, Label dan Jenis(type) dari sumber.

#### Jenis Sumber ####
Sebuah sumber memiliki satu jenis sumber.

Jenis sumber antara lain:
  * Pusat Bahasa (pusba)
  * Sofia Mansoor (sofia)
  * Bahtera (bahtera)
  * Wikipedia (wikipedia)
  * Daisy Subakti (daisy)

## Relasi ##
Sebuah Lemma dapat memiliki beberapa relasi terhadap Lemma lainnya. Sebuah relasi didefinisikan dengan Jenis(type) relasi.

### Jenis Relasi ###
Sebuah lemma memiliki satu jenis relasi.

Jenis relasi antara lain:
  * Sinonim / synonym
  * Antonim / antonym
  * Berkaitan / related
  * Peribahasa / proverb
  * Turunan / descendant
  * Berimbuhan / fixation
  * Majemuk / compound

## Glossary ##
Sebuah lemma dapat memiliki beberapa Glossary. Sebuah Glossary didefinisikan oleh:
  * [Jenis Bahasa](KonsepKateglo#Jenis_Bahasa.md)
  * [Disiplin](KonsepKateglo#Disiplin.md)
  * [Sumber](KonsepKateglo#Sumber.md)

### Jenis Bahasa ###
Sebuah Glossary memiliki satu jenis bahasa. Untuk saat ini Jenis bahasa yang ada di contoh adalah bahasa inggris. ini dapat berubah sewaktu-waktu apabila bahasa lain ditambahkan.

# Use Case Diagram #
![http://kateglo.googlecode.com/svn/branches/kateglox/build/db/kategloxUseCase.jpg](http://kateglo.googlecode.com/svn/branches/kateglox/build/db/kategloxUseCase.jpg)

# Class Diagram #
![http://kateglo.googlecode.com/svn-history/r213/branches/kateglox/build/db/kategloxdb.jpg](http://kateglo.googlecode.com/svn-history/r213/branches/kateglox/build/db/kategloxdb.jpg)

# Component Diagram #
![http://kateglo.googlecode.com/svn/branches/kateglox/build/db/kategloxComponent.jpg](http://kateglo.googlecode.com/svn/branches/kateglox/build/db/kategloxComponent.jpg)