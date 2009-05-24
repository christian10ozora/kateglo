<?php
/**
 * Retrieve data from KBBI
 *
 * OPKODE: 1 = sama dengan, 2 = diawali, 3 = memuat
 * @created 2009-03-30 11:02 <IL>
 * Jw = Jawa, Mk = Minangkabau
 * n = nomina, v = verba, adv = adverbia, a = adjektiva, num = numeralia, p = partikel (artikel, preposisi, konjungsi, interjeksi), pron = pronomina
 * pb = peribahasa
 */
class kbbi
{
	var $param;
	var $defs;
	var $mode;
	var $query;
	var $found = false;
	var $auto_parse = false;
	var $raw_entries; // individual match from kbbi
	var $parsed_entries; // parsed value
	var $clean_entries; // parsed individual
	var $last_lex; // last lexical class
	var $_pair; // temporary

	//$modes = array('sama dengan', 'diawali', 'memuat');

	/*
	 * Get result from KBBI
	 */
	function query($query, $mode)
	{
		$this->query = $query;
		$this->mode = $mode;
		$this->param = array(
			'more' => 0,
			'head' => 0,
			'opcode' => $this->mode,
			'param' => $this->query,
			'perintah' => 'Cari',
			'perintah2' => '',
			'dftkata' => '',
		);
		$this->get_words();
		if ($this->param['dftkata'])
		{
			$words = explode(';', $this->param['dftkata']);
			// $ret .= 'Ditemukan ' . count($words) . ' entri<hr size="1" />' . LF;
			foreach ($words as $word)
			{
				$ret .= $this->define($word) . '' . LF;
			}
			$this->found = true;
		}
		else
		{
			$ret .= 'Tidak ditemukan entri yang sesuai.' . LF;
		}
		return($ret);
	}

	/*
	 * Get result from KBBI
	 */
	function get_words()
	{
		$url = 'http://pusatbahasa.diknas.go.id/kbbi/index.php';
		$data = 'OPKODE=%1$s&PARAM=%2$s&HEAD=%3$s&MORE=%4$s&PERINTAH2=%5$s&%6$s';
		if ($this->param['perintah'] != '')
		{
			$perintah = 'PERINTAH=' . $this->param['perintah'];
		}
		$data = sprintf($data,
			$this->param['opcode'], $this->param['param'], $this->param['head'],
			$this->param['more'], $this->param['perintah2'], $perintah
		);
		$result = $this->get_curl($url, $data);
		$pattern = '/<input type="hidden" name="DFTKATA" value="(.+)" >.+' .
			'<input type="hidden" name="MORE" value="(.+)" >.+' .
			'<input type="hidden" name="HEAD" value="(.+)" >/s';
		preg_match($pattern, $result, $match);
//		var_dump($result);
//		echo('<br />');
		if (is_array($match))
		{
			if ($match[2] == 1)
			{
				$this->param['perintah'] = '';
				$this->param['perintah2'] = 'Berikut';
				$this->param['head'] = $match[3] + 15;
				$this->get_words();
			}
			$this->param['dftkata'] .= $this->param['dftkata'] ? ';' : '';
			$this->param['dftkata'] .= $match[1];
		}
		// if (is_array($match)) return($match[2]);
	}

	/*
	 * Get result from KBBI
	 */
	function define($query)
	{
		$url = 'http://pusatbahasa.diknas.go.id/kbbi/index.php';
		$data .= 'DFTKATA=%2$s&HEAD=0&KATA=%2$s&MORE=0&OPKODE=1&PARAM=&PERINTAH2=Tampilkan';
		$data .= sprintf($data, '1', $query);
		$result = $this->get_curl($url, $data);
		$pattern = '/(<p style=\'margin-left:\.5in;text-indent:-\.5in\'>)(.+)(<\/p>)/s';
		preg_match($pattern, $result, $match);
		if (is_array($match))
		{
			$def = trim($match[2]);
			$this->raw_entries[] = $def;
			$def = str_replace('<br>', '<br><br>', $def);
			$return = $def;
			return($return);
		}
	}

	/*
	 * Get result from KBBI
	 */
	function get_curl($url, $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		return($result);
	}

	/**
	 * TODO:
	 * - Tuju dan menuju: second words following first word
	 * - Info for subindex, e.g. kata Ling 3 a b
	 */
	function parse($phrase)
	{
		// curl
		$this->query($phrase, 1);
		if ($this->found)
		{
			foreach ($this->raw_entries as $value)
			{
				$kbbi_data .= $kbbi_data ? "\n<br>" : '';
				$kbbi_data .= $value;
			}
		}
		else return;

		// parse into lines and process
		$lines = preg_split('/[\n|\r](?:<br>)*(?:<\/i>)*/', $kbbi_data);
		if (is_array($lines))
		{
			// process each line
			$line_count = count($lines);
			for ($i = 0; $i < $line_count; $i++)
			{
				// assume type
				$tmp_type = 'r';

				$pattern = '/([-|~]*<b>.+<\/b>)/U';
				$match = preg_split($pattern, $lines[$i], -1,
					PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$lines[$i] = $match;

				$line_count2 = count($match);
				// normal statement always paired
				if ($line_count2 > 1)
				{
					for ($j = 0; $j < $line_count2 / 2; $j++)
					{
						$pair1 = trim($match[$j * 2]);
						$pair2 = trim($match[$j * 2 + 1]);
						$tmp_def = '';
						$tmp_sample = '';

						// check pair 1 - word or index
						$pair1 = str_replace('&#183;', '', $pair1); // remove &#183; suku kata
						$pair1 = preg_replace('/<sup>\d+<\/sup>/', '', $pair1); // remove superscript
						preg_match('/^[-|~]*<b>.+<\/b>$/', $pair1, $match_bold);
						if (count($match_bold) > 0)
						{
							$pair1 = strip_tags($pair1);
							$pair_key = is_numeric($pair1) ? 'index' : 'phrase';
							$tmp_pair[$i][$j][$pair_key] = trim($pair1);
						}

						// check pair 2 - info or definition

						// pronounciation
						if (preg_match('/^\/([^\/]+)\/(.*)/', $pair2, $pron))
						{
							$tmp_pair[$i][$j]['pron'] = trim($pron[1]);
							$pair2 = trim($pron[2]);
						}

						// TODO: possibility of more than 2 tags
						preg_match('/^([-|~]*<i>.+<\/i>)(.*)$/U', $pair2, $match_italic);
						if (count($match_italic) > 0)
						{
							$tmp_pair[$i][$j]['info'] = trim(strip_tags($match_italic[1]));
							$pair2 = trim($match_italic[2]);
							// definition, watch for possible additional <i> tags
							if ($pair2 != '')
							{
								$tmp_def = trim($match_italic[2]);
								preg_match('/^([-|~]*<i>.+<\/i>)(.*)$/U', $pair2, $match_italic);
								if (count($match_italic) > 0)
								{
									$tmp_pair[$i][$j]['info'] .= ' ' . trim(strip_tags($match_italic[1]));
									$tmp_def = trim($match_italic[2]);
								}
							}
						}
						else
						{
							if ($pair2) $tmp_def = trim($pair2);
						}

						// phrase that contains number
						$tmp_phrase = $tmp_pair[$i][$j]['phrase'];
						preg_match('/^(.+) (\d+)$/U', $tmp_phrase, $phrase_num);
						if (count($phrase_num) > 0)
						{
							$tmp_phrase = $phrase_num[1];
							$tmp_pair[$i][$j]['index'] = $phrase_num[2];
						}

						// clean up definition
						if ($tmp_def == ',') unset($tmp_def);
						if ($tmp_def) $tmp_def = strip_tags($tmp_def);
						if (strpos($tmp_phrase, '--') !== false) $tmp_type = 'c';
						if (strpos($tmp_phrase, '~') !== false) $tmp_type = 'c';

						// parse info
						if ($tmp_pair[$i][$j]['info'] != '')
							$this->parse_info_lexical(&$tmp_pair[$i][$j]);

						// sample
						if (strpos($tmp_def, ':'))
						{
							if ($sample = split(':', $tmp_def))
							{
								$tmp_def = trim($sample[0]);
								$tmp_sample = trim(strip_tags($sample[1]));
							}
						}

						// hack a, b
						if (strlen($tmp_phrase) == 1)
						{
							unset($tmp_pair[$i][$j]['phrase']);
							$tmp_phrase = '';
						}

						// syntax like meng-
						$tmp_phrase = trim(preg_replace('/\(.+\)$/U', '', $tmp_phrase));

						// syntax like U, u
						$tmp_phrase = trim(preg_replace('/,.+$/U', '', $tmp_phrase));

						// syntax like ? apotek
						$tmp_def1 = trim(preg_replace('/^\?\s*(.+)$/U', '\1', $tmp_def));
						if ($tmp_def1 != $tmp_def)
						{
							$tmp_def = 'lihat ' . $tmp_def1;
							$tmp_pair[$i][$j]['see'] = $tmp_def1;
						}

						// write
						if ($tmp_phrase) $tmp_pair[$i][$j]['phrase'] = $tmp_phrase;
						if ($tmp_def) $tmp_pair[$i][$j]['def'] = $tmp_def;
						if ($tmp_sample) $tmp_pair[$i][$j]['sample'] = $tmp_sample;
						if ($tmp_type) $tmp_pair[$i][$j]['type'] = $tmp_type;

						// look back
						if ($j > 0)
						{
							// for two definition
							if ($tmp_pair[$i][$j]['phrase'] != $tmp_pair[$i][$j - 1]['phrase'])
							{
								if (!$tmp_pair[$i][$j - 1]['def'] && strlen($tmp_pair[$i][$j]['phrase']) > 1)
								{
									$tmp_pair[$i][$j - 1]['def'] = 'lihat ' . $tmp_pair[$i][$j]['phrase'];
									$tmp_pair[$i][$j - 1]['see'] = $tmp_pair[$i][$j]['phrase'];
								}
							}
						}
					}
				}
				// .. but sometimes proverb isn't paired
				else
				{
					// hack if it's not started with <i>
					if (strpos(substr($lines[$i][0], 0, 10), '<i>') === false)
						$lines[$i][0] = '<i>' . $lines[$i][0];
					// split into word and meaning
					$match = preg_split('/([-|~]*<i>)/U', $lines[$i][0], -1,
						PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
					$line_count2 = count($match);
					for ($j = 0; $j < $line_count2 / 2; $j++)
					{
						$proverb_pair = trim($match[$j * 2]) . ' ' . trim($match[$j * 2 + 1]);
						$proverb_array = explode('</i>', $proverb_pair);
						$tmp_phrase = trim(strip_tags($proverb_array[0]));
						$tmp_phrase = preg_replace('/,\s*pb/U', '', $tmp_phrase);
						$tmp_phrase = trim($tmp_phrase);
						$tmp_def = trim($proverb_array[1]);
						$tmp_pair[$i][] = array(
							'proverb' => $tmp_phrase,
							'def' => $tmp_def,
							'is_proverb' => true,
						);
					}
				}
			}
		}

		$this->_pair = $tmp_pair;
		$pair_count = count($tmp_pair);
		for ($i = 0; $i < $pair_count; $i++)
		{
			$pair_count2 = count($tmp_pair[$i]);
			for ($j = 0; $j < $pair_count2; $j++)
			{
				// ilmu: fisika
				if ($tmp_pair[$i][$j]['def'] == ';' && $tmp_pair[$i][$j - 1]['def'] = 'lihat')
				{
					$tmp_pair[$i][$j - 1]['def'] = 'lihat ' . $tmp_pair[$i][$j]['phrase'];
					$tmp_pair[$i][$j - 1]['see'] = $tmp_pair[$i][$j]['phrase'];
					unset($tmp_pair[$i][$j]);
				}
			}
		}

		// put into array
		$i = 0;
		foreach ($tmp_pair as $pair_def)
		{
			foreach ($pair_def as $phrase_def)
			{

				// abbreviation
				$abbrev = array(
					'dl' => 'dalam',
					'dng' => 'dengan',
					'dr' => 'dari',
					'dp' => 'daripada',
					'kpd' => 'kepada',
					'krn' => 'karena',
					'msl' => 'misal',
					'pd' => 'pada',
					'sbg' => 'sebagai',
					'spt' => 'seperti',
					'tsb' => 'tersebut',
					'tt' => 'tentang',
					'yg' => 'yang',
				);
				foreach ($abbrev as $key => $value)
				{
					$pattern = '/\b' . $key . '\b/';
					if ($phrase_def['sample'])
						$phrase_def['sample'] = preg_replace($pattern, $value, $phrase_def['sample']);
					if ($phrase_def['def'])
						$phrase_def['def'] = preg_replace($pattern, $value, $phrase_def['def']);
				}

				// fixing
				if ($phrase_def['sample'])
					$phrase_def['sample'] = preg_replace('/;$/U', '', $phrase_def['sample']);
				if ($phrase_def['def'])
					$phrase_def['def'] = preg_replace('/;$/U', '', $phrase_def['def']);
				if ($phrase_def['phrase'])
					$phrase_def['phrase'] = preg_replace('/^- /U', '-- ', $phrase_def['phrase']);

				// root word
				$tmp_phrase = $phrase_def['proverb'] ? $phrase_def['proverb'] : $phrase_def['phrase'];
				if (strpos($tmp_phrase, '--') !== false || strpos($tmp_phrase, '~') !== false)
				{
					$tmp_phrase = str_replace('--', $last_phrase, $tmp_phrase);
					$tmp_phrase = str_replace('~', $last_phrase, $tmp_phrase);
				}
				else
					if ($tmp_phrase && !$phrase_def['proverb']) $last_phrase = $tmp_phrase;

				// see if it's a compound word
				if ($phrase_def['type'] == 'c')
				{
					if ($tmp_phrase)
						$last_compound = $tmp_phrase;
					else
						$tmp_phrase = $last_compound;
				}

				// push def
				if ($tmp_phrase)
				{
					if ($phrase_def['proverb'])
						$phrase_def['proverb'] = $tmp_phrase;
					else
						$phrase_def['phrase'] = $tmp_phrase;
				}
				if (!$phrase_def['phrase']) $phrase_def['phrase'] = $last_phrase;

				// main
				$defs = &$this->defs[$phrase_def['phrase']];
				if ($phrase_def['pron']) $defs['pron'] = $phrase_def['pron'];
				if ($phrase_def['type']) $defs['type'] = $phrase_def['type'];

				// lexical class and info
				if (count($defs['definitions']) <= 0)
				{
					if ($phrase_def['lex_class']) $defs['lex_class'] = $phrase_def['lex_class'];
					if ($phrase_def['info']) $defs['info'] = $phrase_def['info'];
				}


				// proverb
				if ($phrase_def['is_proverb'])
				{
					$proverb_index = count($defs['proverbs']);
					if ($phrase_def['proverb'])
						$defs['proverbs'][$proverb_index]['proverb'] = $phrase_def['proverb'];
					if ($phrase_def['def'])
						$defs['proverbs'][$proverb_index]['def'] = $phrase_def['def'];
				}
				// definition
				else
				{
					if ($phrase_def['def'])
					{
						$def_index = count($defs['definitions']);
						$defs['definitions'][$def_index]['text'] = $phrase_def['def'];
						if ($phrase_def['see'])
							$defs['definitions'][$def_index]['see'] = $phrase_def['see'];
						if ($phrase_def['sample'])
							$defs['definitions'][$def_index]['sample'] = $phrase_def['sample'];
						if ($phrase_def['lex_class'] && $phrase_def['lex_class'] != $defs['lex_class'])
							$defs['definitions'][$def_index]['lex_class'] = $phrase_def['lex_class'];
						if ($phrase_def['info'] && $phrase_def['info'] != $defs['info'])
							$defs['definitions'][$def_index]['info'] = $phrase_def['info'];
					}
				}
			}
		}

		// final
		$i = 0;
		foreach ($this->defs as $def_key => &$def)
		{
			// affix
			if ($i > 0 && $def['type'] == 'r') $def['type'] = 'f';
			// last type
			if ($def['type'] == 'c' && $last_type == 'f')
				$def['type'] = 'f';
			else
				$last_type = $def['type'];
			// lexical
			if ($def['lex_class'])
				$last_lexical = $def['lex_class'];
			else
				$def['lex_class'] = $last_lexical;
			// synonym
			$this->parse_synonym(&$def);
			// definitions
			$j = 0;
			if ($def['definitions'])
			{
				foreach ($def['definitions'] as &$def_item)
				{
					$j++;
					$def_item['index'] = $j;
				}
			}
			// increment
			$i++;
		}

	}

	function parse_info_lexical(&$item)
	{
		if ($item['info'])
		{
			$item['info'] = preg_replace('/,$/U', '', $item['info']);
			$infos = explode(' ', $item['info']);
			$lexical = '';
			$other = '';
			foreach ($infos as $info)
			{
				if (in_array($info, array('n', 'v', 'a', 'adv', 'p', 'num', 'pron')))
				{
					if ($info == 'a') $info = 'adj';
					$lexical .= $lexical ? ', ' : '';
					$lexical .= $info;
				}
				else
				{
					$other .= $other ? ', ' : '';
					$other .= $info;
				}
			}
			if ($lexical) $item['lex_class'] = $lexical;
			if ($other)
				$item['info'] = $other;
			else
				unset($item['info']);
		}
	}

	/**
	 */
	function parse_synonym(&$clean)
	{
		if ($clean['definitions'])
		{
			foreach ($clean['definitions'] as $def_key => $def)
			{
				$def_items = explode(';', $def['text']);
				if ($def_items)
				{
					foreach ($def_items as $def_item)
					{
						$def_item = trim($def_item);
						$space_count = substr_count($def_item, ' ');
						if ($space_count < 1)
							$clean['synonyms'][] = $def_item;
					}
				}
			}
		}
	}

	function get_local()
	{
		global $_SERVER;

		// get local file
		$url = 'http://127.0.0.1/kateglo/sandbox/kbbi3-2001-big.html';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);

		// parse
		preg_match_all('/<p>LEMA:(.+)( \(\d+\))?<br>/sU', $result, $matches);
		$ret = array();
		foreach ($matches[1] as $phrase)
		{
			$i++;
			$phrase = trim($phrase);
			$ret[] = $phrase;
//			if (is_array($ret))
//			{
//				if (!in_array($phrase, $ret)) $ret[] = $phrase;
//			}
		}
		return($ret);
	}
};
?>