# Search #

Search di Kateglo menggunakan Lucene. Bagaimana menggunakan Lucene bisa dibaca di website [Zend](http://framework.zend.com/manual/en/zend.search.lucene.query-language.html).

Index dari Kateglo ada 2 macam, yaitu:
  * Lemma
  * Glossary

Masing-masing memiliki fields untuk membatasi pencarian.

## Lemma ##

Pencarian Lemma bisa dibatasi dengan Field berikut:

  * lemma
  * type
    * root
    * fixiation
    * descendant
    * proverb
  * definition
  * lexical
    * noun
    * verb
    * adjective
    * adverb
    * pronoun
    * numeric
    * other
  * defSource
  * defSourceType
    * pusba
    * sofia
    * bahtera
    * wikipedia
    * daisy
  * relationType
    * synonym
    * antonym
    * related
    * proverb
    * descendant
    * fixation
    * compound
  * glossary
  * locale
    * en
  * discipline
  * gloSource
  * gloSourceType

## Glossary ##

Pencarian Lemma bisa dibatasi dengan Field berikut:

  * glossary
  * lemma
  * locale
  * localeName
  * discipline
  * disciplineName
  * gloSource
  * gloSourceType
  * definition
  * lexical
  * defSource
  * defSourceType
  * relationType