<?php
/**
 * Phrase class
 */
require_once($base_dir . '/modules/class_kbbi.php');
require_once($base_dir . '/modules/class_glossary.php');
//require_once($base_dir . '/modules/class_opentran.php');
class dictionary extends page
{
	var $kbbi;
	var $phrase;

	/**
	 * Constructor
	 */
	function dictionary(&$db, &$auth, $msg)
	{
		parent::page(&$db, &$auth, $msg);
	}

	/**
	 *
	 */
	function process()
	{
		global $_GET;
		global $_SERVER;
		$redirect = true;
		// process depending on action
		switch ($_GET['action'])
		{
			case 'form':
				if ($this->is_post && $this->auth->checkAuth())
					$this->save_form();
				break;
			case 'delete':
				if ($_GET['phrase'])
				{
					if ($this->auth->checkAuth())
						$this->delete($_GET['phrase']);
					redir('./?mod=dictionary&action=view&phrase=' . $_GET['phrase']);
				}
				break;
			case 'kbbi':
				if ($_GET['phrase'])
				{
					$this->kbbi = new kbbi($this->msg, &$this->db);
					$this->kbbi->parse($_GET['phrase']);
					if ($this->kbbi->found) $this->save_kbbi($_GET['phrase']);
					redir('./?mod=dictionary&action=view&phrase=' . $_GET['phrase']);
				}
				break;
			case 'view':
				$redirect = false;
				break;
		}
		// redirect if none found. psycological effect
		if ($_GET['phrase'] && $redirect && !$this->get_list())
			redir('./?mod=dictionary&action=view&phrase=' . $_GET['phrase']);
	}

	/**
	 *
	 */
	function show()
	{
		global $_GET;
		switch ($_GET['action'])
		{
			case 'view':
				if ($_GET['phrase'])
					$this->title = $_GET['phrase'];
				//$ret .= $this->show_phrase_brief();
				$ret .= $this->show_phrase();
				break;
			case 'form':
				if ($this->auth->checkAuth())
					$ret .= $this->show_form();
				break;
			default:
				$ret .= sprintf('<h1>%1$s</h1>' . LF, $this->msg['dictionary']);
				// menu
				if ($this->auth->checkAuth())
				{
					$actions = array(
						'new' => array('url' => './?mod=dictionary&action=form'),
					);
					$ret .= $this->get_action_buttons($actions);
				}
				$ret .= $this->show_search();
				$ret .= $this->show_list();
				break;
		}
		return($ret);
	}

	/**
	 * Show list of words
	 */
	function show_list()
	{

		// index or phrase
		if (!$_GET['phrase'] && !$_GET['lex'] && !$_GET['type'] && !$_GET['idx'] && !$_GET['srch'])
		{
			$ret .= sprintf('<p><strong><a href="%1$s">%2$s</a></strong></p>' . LF,
				'./?mod=dictionary&srch=all', $this->msg['all_entry']
			);

			$ret .= '<p><strong>' . $this->msg['dict_by_letter'] . '</strong></p>' . LF;
			$ret .= '<blockquote>' . LF;
			$i = 0;
			$ret .= '<a href="./?mod=dictionary&idx=-">-</a>';
			for ($i = 65; $i <= 90; $i++)
			{
				$ret .= '; ' . LF;
				$ret .= sprintf('<a href="./?mod=dictionary&idx=%1$s">%1$s</a>', chr($i));
			}
			$ret .= LF . '</blockquote>' . LF;
			$ret .= '<p><strong>' . $this->msg['dict_by_lex'] . '</strong></p>' . LF;
			$rows = $this->db->get_rows('SELECT * FROM lexical_class ORDER BY sort_order;');
			if ($row_count = $this->db->num_rows)
			{
				$ret .= '<blockquote>' . LF;
				$i = 0;
				foreach ($rows as $row)
				{
					if ($i > 0) $ret .= '; ' . LF;
					$ret .= sprintf('<a href="./?mod=dictionary&lex=%2$s">%1$s</a>',
						$row['lex_class_name'], $row['lex_class']);
					$i++;
				}
				$ret .= LF . '</blockquote>' . LF;
			}
			$ret .= '<p><strong>' . $this->msg['dict_by_type'] . '</strong></p>' . LF;
			$rows = $this->db->get_rows('SELECT * FROM phrase_type ORDER BY sort_order;');
			if ($row_count = $this->db->num_rows)
			{
				$ret .= '<blockquote>' . LF;
				$i = 0;
				foreach ($rows as $row)
				{
					if ($i > 0) $ret .= '; ' . LF;
					$ret .= sprintf('<a href="./?mod=dictionary&type=%2$s">%1$s</a>',
						$row['phrase_type_name'], $row['phrase_type']);
					$i++;
				}
				$ret .= LF . '</blockquote>' . LF;
			}
			return($ret);
		}

		// get result
		$operators = array(
			'1' => array('type'=>'LIKE', 'open'=>'%', 'close'=>'%'),
			'2' => array('type'=>'REGEXP', 'open'=>'[[:<:]]', 'close'=>'[[:>:]]'),
			'3' => array('type'=>'=', 'open'=>'', 'close'=>''),
			'4' => array('type'=>'LIKE', 'open'=>'', 'close'=>'%'),
			'5' => array('type'=>'LIKE', 'open'=>'%', 'close'=>''),
		);
		if (!array_key_exists($_GET['op'], $operators)) $_GET['op'] = '1';
		$op_open = $operators[$_GET['op']]['open'];
		$op_close = $operators[$_GET['op']]['close'];
		$op_type = $operators[$_GET['op']]['type'];
		$op_template = 'a.%1$s %2$s \'%3$s%4$s%5$s\'';
		if ($_GET['phrase'])
		{
			$where .= $where ? ' AND ' : ' WHERE ';
			$where .= sprintf($op_template, 'phrase', $op_type, $op_open,
				$this->db->quote($_GET['phrase'], null, false), $op_close);
		}
		if ($_GET['lex'])
		{
			$where .= $where ? ' AND ' : ' WHERE ';
			$where .= ' a.lex_class = ' . $this->db->quote($_GET['lex']) . ' ';
		}
		if ($_GET['type'])
		{
			$where .= $where ? ' AND ' : ' WHERE ';
			$where .= ' a.phrase_type =' . $this->db->quote($_GET['type']) . ' ';
		}
		if ($_GET['idx'])
		{
			$where .= $where ? ' AND ' : ' WHERE ';
			$_GET['idx'] = substr($_GET['idx'], 0, 1);
			$idx_ascii = ord($_GET['idx']);
			if (($idx_ascii >= 65 && $idx_ascii <= 90) ||
				($idx_ascii >= 97 && $idx_ascii <= 122))
				$where .= ' LEFT(a.phrase, 1) = ' . $this->db->quote($_GET['idx']) . ' ';
			else
				$where .= ' LEFT(a.phrase, 1) REGEXP \'^[^[:alpha:]]\' ';
		}

		$cols = 'a.phrase, a.lex_class, a.actual_phrase, a.info';
		$from = 'FROM phrase a ' . $where . ' ';
		$from .= 'ORDER BY a.phrase ';
		$this->db->defaults['rperpage'] = 20;
		$rows = $this->db->get_rows_paged($cols, $from);
		$row_count = $this->db->num_rows;

		// result
		if ($row_count > 0)
		{
			// params
			$nav_html = $this->db->get_page_nav(true);
			$start_row = $this->db->pager['rbegin'];
			$url_detail = './?mod=dictionary&action=view&phrase=';

			// get definitions
			foreach ($rows as $row)
			{
				$found .= $found ? ', ' : '';
				$found .= $this->db->_db->quote($row['phrase']);
			}
			$sql = 'SELECT * FROM definition WHERE phrase IN (%1$s)
				ORDER BY phrase, def_num, def_uid;';
			$defs = $this->db->get_rows(sprintf($sql, $found));
			for ($i = 0; $i < $row_count; $i++)
			{
				foreach ($defs as $def)
				{
					if (strtolower($rows[$i]['phrase']) == strtolower($def['phrase']))
					{
						$rows[$i]['defs'][] = $def;
					}
				}
			}

			// print result
			$ret .= '<p>' . $nav_html . '</p>' . LF;
			foreach ($rows as $row)
			{
				$i = 0;
				$def_count = count($row['defs']);
				$ret .= '<p>';
				$ret .= sprintf('<b><a href="%2$s%1$s">%1$s</a></b> ',
					$row['phrase'],
					$url_detail
				);
				$ret .= '</p>' . LF;
				$ret .= '<blockquote>';
				if ($row['actual_phrase'])
				{
					$ret .= sprintf('&rarr; <a href="%2$s%1$s">%1$s</a>',
						$row['actual_phrase'], $url_detail);
				}
				else
				{
					$ret .= $row['lex_class'] ? '<i>' . $row['lex_class'] . '</i> ' : '';
					$ret .= $row['info'] ? '<i>(' . $row['info'] . ')</i> ' : '';
					if ($def_count)
					{
						foreach ($row['defs'] as $def)
						{
							$i++;
							$ret .= sprintf('%1$s%3$s%4$s%2$s; ',
								$def_count > 1 ? '<b>' . $i . '</b> ' : '',
								$def['see'] ? sprintf('&rarr; <a href="%2$s%1$s">%1$s</a>',
									$def['see'], $url_detail) : $def['def_text'],
								$def['lex_class'] && ($def['lex_class'] != $row['lex_class']) ? '<i>' . $def['lex_class'] . '</i> ' : '',
								$def['discipline'] ? '<i>(' . $def['discipline'] . ')</i> ' : ''
							);
						}
					}
				}
				$ret .= '</blockquote>' . LF;
			}
			$ret .= '<p>' . $nav_html . '</p>' . LF;

//			$mark = ceil($row_count / 3);
//			$j = 1;
//			$ret .= '<p>' . $nav_html . '</p>' . LF;
//			$ret .= '<table width="100%"><tr valign="top">' . LF;
//			foreach ($rows as $row)
//			{
//				$i++;
//				if ($j == $i)
//				{
//					if ($i > 1) $ret .= '</ul></td>' . LF;
//					$ret .= '<td width="33%"><ul start="' . ($start_row + $i - 1) . '">' . LF;
//					$j += $mark;
//				}
//				$tmp = '<li><a href="./?mod=dictionary&action=view&phrase=%1$s">%1$s</a></li>' . LF;
//				$ret .= sprintf($tmp, $row['phrase']);
//			}
//			$ret .= '</ul></td>' . LF;
//			$ret .= '</tr></table>' . LF;
//			$ret .= '<p>' . $nav_html . '</p>' . LF;
		}
		else
			$ret .= sprintf('<p>Frasa yang dicari tidak ditemukan. <a href="./?mod=dictionary&action=view&phrase=%1$s">Coba lagi</a>?</p>' . LF, $_GET['phrase']);
		return($ret);
	}

	/**
	 * Get dictionary detail page
	 */
	function show_phrase()
	{
		global $_GET;

		$lex_classes = $this->db->get_row_assoc(
			'SELECT * FROM lexical_class', 'lex_class', 'lex_class_name');

		$this->phrase = $this->get_phrase();
		$phrase = $this->phrase;
		$this->kbbi = new kbbi($this->msg, &$this->db);

		// if it's not marked created
		if (!$phrase['created'])
		{
			$this->kbbi->force_refresh = true;
			$this->kbbi->parse($_GET['phrase']);
			if ($this->kbbi->found) $this->save_kbbi($_GET['phrase']);
			$this->kbbi->force_refresh = false;
			$this->phrase = $this->get_phrase();
			$phrase = $this->phrase;
		}

		// if it's not marked created
		if (!$phrase['kbbi_updated'])
		{
			$this->kbbi->force_refresh = true;
			$this->kbbi->parse($_GET['phrase']);
			if ($this->kbbi->found) $this->save_kbbi2($_GET['phrase']);
			$this->kbbi->force_refresh = false;
			$this->phrase = $this->get_phrase();
			$phrase = $this->phrase;
		}

		// header
		$ret .= sprintf('<h1>%1$s</h1>' . LF, $_GET['phrase']);

		// kbbi header
		$ret .= '<table width="100%" cellpadding="0" cellspacing="0"><tr valign="top"><td width="60%">' . LF;

		// buttons
		if ($this->auth->checkAuth())
		{
			$actions = array(
				'new' => array(
					'url' => './?mod=dictionary&action=form',
				),
				'edit' => array(
					'url' => './?mod=dictionary&action=form&phrase=' . $_GET['phrase'],
				),
				'delete' => array(
					'url' => './?mod=dictionary&action=delete&phrase=' . $_GET['phrase'],
				),
				'get_kbbi' => array(
					'url' => './?mod=dictionary&action=kbbi&phrase=' . $_GET['phrase'],
				),
			);
			$ret .= $this->get_action_buttons($actions, $phrase ? null : array('new', 'get_kbbi'));
		}

		// found?
		if ($phrase)
		{
			$i = 0;

			// definition: get from actual phrase or definition
			if ($phrase['actual_phrase'])
				$defs = array(array(
					'def_num' => 1,
					'def_text' => $phrase['actual_phrase'],
					'see' => $phrase['actual_phrase'],
				));
			else
				$defs = $phrase['definition'];

			// show definition
			$def_count = count($defs);
			$ret .= '<p><strong>' . $this->msg['definition']. ':</strong></p>' . LF;
			if ($defs)
			{
				if ($def_count == 1) $ret .= '<blockquote>';
				if ($def_count > 1) $ret .= '<ol>' . LF;
				foreach ($defs as $def)
				{
					// discipline
					$discipline = ($i == 0) ? $phrase['info'] : '';
					if ($def['discipline'])
					{
						$discipline .= $discipline ? ', ' : '';
						$discipline .= $def['discipline'];
					}
					$dsc = $discipline ? '<em>(' . $this->get_abbrev($discipline) . ')</em> ' : '';
					// lex class
					$lex_name = $def['lex_class'];
					$lex_title = $lex_classes[$def['lex_class']];
					if ($def['lex_class_ref'])
					{
						$lex_name = sprintf(
							'<a href="./?mod=dictionary&action=view&phrase=%2$s" title="%3$s">%1$s</a>',
							$def['lex_class'],
							$def['lex_class_ref'],
							$lex_title
						);
					}
					$lex = $def['lex_class'] ? '<em>' . $lex_name . '</em> ' : '';
					// start
					if ($def_count > 1) $ret .= '<li>';
					if ($def['see'])
					{
						$ret .= sprintf('%3$s%4$s&rarr; <a href="%2$s%1$s">%1$s</a>',
							$def['see'],
							'./?mod=dictionary&action=view&phrase=',
							$lex,
							$dsc
						);
					}
					else
					{
						$ret .= sprintf('%4$s%2$s%1$s%3$s',
							$def['def_text'],
							$dsc,
							$def['sample'] ? ': <em>' . $def['sample'] . '</em> ' : '',
							$lex
						);
					}
					if ($def_count > 1) $ret .= '</li>' . LF;
					$i++;
				}
				if ($def_count > 1) $ret .= '</ol>' . LF;
				if ($def_count == 1) $ret .= '</blockquote>' . LF;
			}
			else
				$ret .= '<p>' . $this->msg['na']. '</p>' . LF;

			// misc
			$template = '<p><strong>%1$s:</strong></p>' . LF . '<blockquote>%2$s</blockquote>' . LF;
			if ($phrase['lex_class_name'])
			{
				$lex_name = $phrase['lex_class_name'];
				if ($phrase['lex_class_ref'])
					$lex_name = sprintf('<a href="./?mod=dictionary&action=view&phrase=%2$s">%1$s</a>',
						$phrase['lex_class_name'],
						$phrase['lex_class_ref']);
				$ret .= sprintf($template, $this->msg['lex_class'], $lex_name);
			}
			if ($phrase['pronounciation'])
				$ret .= sprintf($template, $this->msg['pronounciation'], $phrase['pronounciation']);
			if ($phrase['etymology'])
				$ret .= sprintf($template, $this->msg['etymology'], $phrase['etymology']);
			if ($phrase['notes'])
				$ret .= sprintf($template, $this->msg['notes'], $phrase['notes']);
			if ($phrase['root'])
				$ret .= sprintf($template, $this->msg['root_phrase'],
					$this->merge_phrase_list($phrase['root'], 'root_phrase'));
			if ($phrase['roget_class'])
				$ret .= sprintf($template, $this->msg['roget_class'], $phrase['roget_name']);
			if ($phrase['ref_source'])
				$ret .= sprintf($template, $this->msg['ref_source'], $phrase['ref_source_name']);

			// reference
			if ($phrase['reference'])
			{
				$ret .= '<p><strong>' . $this->msg['external_ref']. ':</strong></p>' . LF;
				$ret .= '<ul>' . LF;
				foreach ($phrase['reference'] as $reference)
				{
					$ret .= sprintf(
						'<li><a href="%2$s">%1$s</a></li>' . LF,
						$reference['label'] ? $reference['label'] : $reference['url'],
						$reference['url']
					);
				}
				$ret .= '</ul>' . LF;
			}

			// relation and derivation
			$ret .= $this->show_relation($phrase, 'related_phrase');

			// peribahasa
			if ($phrase['proverbs'])
			{
				$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['proverb']);
				$ret .= '<ul>' . LF;
				foreach ($phrase['proverbs'] as $proverb)
				{
					$ret .= sprintf('<li><em>%1$s</em>: %2$s</li>' . LF, $proverb['proverb'], $proverb['meaning']);
				}
				$ret .= '</ul>' . LF;
			}

			// translation
			if ($phrase['translations'])
			{
				$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['translation']);
				$ret .= '<ul>' . LF;
				foreach ($phrase['translations'] as $translation)
				{
					$ret .= sprintf('<li><em>%1$s</em>: %2$s</li>' . LF, $translation['ref_source'], $translation['translation']);
				}
				$ret .= '</ul>' . LF;
			}

			// additional process: update relation
			if ($phrase['relation']['d'])
			{
				$word = $_GET['phrase'];
				$tmp = '';
				foreach ($phrase['relation']['d'] as $rel)
				{
					$pattern = '/\b' . $word . '\b/';
					if (preg_match($pattern, $rel['related_phrase'])
						&& !strpos($rel['related_phrase'], '-'))
					{
						$tmp .= $tmp ? ', ' : '';
						$tmp .= "'" . $rel['related_phrase'] . "'";
					}
				}
				if ($tmp)
				{
					$sql = sprintf('UPDATE relation SET
						rel_type = \'c\',
						updater = \'%3$s\',
						updated = NOW()
						WHERE root_phrase = \'%1$s\'
						AND related_phrase IN (%2$s);', $word, $tmp, 'compounder');
					$this->db->exec($sql);
				}
			}

			// additional process: insert new root
			if ($phrase['root'])
			{
				$words = "'" . str_replace(' ', "', '", $_GET['phrase']) .  "'";
				$sql = sprintf('
					INSERT INTO relation
					(root_phrase, related_phrase, rel_type, updater, updated)
					SELECT phrase, \'%2$s\', \'c\', \'rooter\', NOW()
					FROM phrase
					WHERE phrase <> \'%2$s\' AND phrase IN (%1$s) AND phrase NOT IN
						(SELECT root_phrase FROM relation
						WHERE related_phrase = \'%2$s\');',
				$words, $_GET['phrase']);
				$this->db->exec($sql);
			}
		}
		else
		{
			$ret .= sprintf('<p>%1$s</p>', sprintf($this->msg['phrase_na'], $_GET['phrase']));
			// derivation and relation
			$this->get_relation(&$phrase, 'related_phrase', true);
			$ret .= $this->show_relation($phrase, 'root_phrase');
		}

		// glosarium
		$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['glossary']);
		$_GET['lang'] = 'id';
		$glossary = new glossary(&$this->db, &$this->auth, $this->msg);
		$glossary->sublist = true;
		$ret .= $glossary->show_result();

		// kbbi
		$ret .= $this->show_kbbi();

		// navigation
		if ($phrase)
		{
			$ret .= '<p align="center">' . $this->get_prev_next($_GET['phrase']) . '</p>';
		}

		return($ret);
	}

	/**
	 * Alternate display
	 */
	function show_phrase2()
	{
		global $_GET;

		$lex_classes = $this->db->get_row_assoc(
			'SELECT * FROM lexical_class', 'lex_class', 'lex_class_name');

		$this->phrase = $this->get_phrase();
		$phrase = $this->phrase;
		$this->kbbi = new kbbi($this->msg, &$this->db);

		// if it's not marked created
		if (!$phrase['created'])
		{
			$this->kbbi->force_refresh = true;
			$this->kbbi->parse($_GET['phrase']);
			if ($this->kbbi->found) $this->save_kbbi($_GET['phrase']);
			$this->kbbi->force_refresh = false;
			$this->phrase = $this->get_phrase();
			$phrase = $this->phrase;
		}

		// if it's not marked created
		if (!$phrase['kbbi_updated'])
		{
			$this->kbbi->force_refresh = true;
			$this->kbbi->parse($_GET['phrase']);
			if ($this->kbbi->found) $this->save_kbbi2($_GET['phrase']);
			$this->kbbi->force_refresh = false;
			$this->phrase = $this->get_phrase();
			$phrase = $this->phrase;
		}

		// header
		$ret .= sprintf('<h1>%1$s</h1>' . LF, $_GET['phrase']);

		// kbbi header
		$ret .= '<table width="100%" cellpadding="0" cellspacing="0"><tr valign="top"><td width="60%">' . LF;

		if ($this->auth->checkAuth())
		{
			$actions = array(
				'new' => array(
					'url' => './?mod=dictionary&action=form',
				),
				'edit' => array(
					'url' => './?mod=dictionary&action=form&phrase=' . $_GET['phrase'],
				),
				'delete' => array(
					'url' => './?mod=dictionary&action=delete&phrase=' . $_GET['phrase'],
				),
				'get_kbbi' => array(
					'url' => './?mod=dictionary&action=kbbi&phrase=' . $_GET['phrase'],
				),
			);
			$ret .= $this->get_action_buttons($actions, $phrase ? null : array('new', 'get_kbbi'));
		}

		// found?
		if ($phrase)
		{
			$i = 0;

			// definition: get from actual phrase or definition
			if ($phrase['actual_phrase'])
				$defs = array(array(
					'def_num' => 1,
					'def_text' => $phrase['actual_phrase'],
					'see' => $phrase['actual_phrase'],
				));
			else
				$defs = $phrase['definition'];

			$ret .= '<p>';
			$ret .= sprintf('<strong>%1$s</strong> ', $phrase['phrase']);
			if ($phrase['pronounciation'])
				$ret .= sprintf(' /%2$s/ ', $this->msg['pronounciation'], $phrase['pronounciation']);
			if ($phrase['lex_class_name'])
			{
				$lex_name = $phrase['lex_class_name'];
				if ($phrase['lex_class_ref'])
					$lex_name = sprintf('<a href="./?mod=dictionary&action=view&phrase=%2$s">%1$s</a>',
						$phrase['lex_class_name'],
						$phrase['lex_class_ref']);
				$ret .= sprintf(' <em>%2$s</em> ', $this->msg['lex_class'], strtolower($lex_name));
			}

			// show definition
			$def_count = count($defs);
			if ($defs)
			{
				foreach ($defs as $def)
				{
					$discipline = ($i == 0) ? $phrase['info'] : '';
					if ($def_count > 1) $ret .= '<strong>' . ($i + 1) . '.</strong> ';
					if ($def['see'])
					{
						$ret .= sprintf('lihat <b><a href="./?mod=dictionary&action=view&phrase=%2$s">%1$s</a></b>',
							$def['see'],
							$def['see']
						);
					}
					else
					{
						if ($def['discipline'])
						{
							$discipline .= $discipline ? ', ' : '';
							$discipline .= $def['discipline'];
						}
						$lex_name = $def['lex_class'];
						$lex_title = $lex_classes[$def['lex_class']];
						if ($def['lex_class_ref'])
							$lex_name = sprintf(
								'<a href="./?mod=dictionary&action=view&phrase=%2$s" title="%3$s">%1$s</a>',
								$def['lex_class'],
								$def['lex_class_ref'],
								$lex_title
							);
						$ret .= sprintf('%4$s%2$s%1$s%3$s',
							$def['def_text'],
							$discipline ? '<em>(' . $this->get_abbrev($discipline) . ')</em> ' : '',
							$def['sample'] ? ': <em>' . $def['sample'] . '</em> ' : '',
							$def['lex_class'] ? '<em>' . $lex_name . '</em> ' : ''
						);
					}
					if ($def_count > 1) $ret .= '; ';
					$i++;
				}
			}
			else
				$ret .= $this->msg['na'];
			$ret .= '</p>' . LF;

			// misc
			$template = '<p><b>%1$s:</b><br />' . LF . '%2$s</p>' . LF;
			$template = '<dl><dt>%1$s:</dt><dd>' . LF . '%2$s</dd></dl>' . LF;
			$template = '<p><em>%1$s:</em> ' . LF . '%2$s</p>' . LF;

			if ($phrase['etymology'])
				$ret .= sprintf($template, $this->msg['etymology'], $phrase['etymology']);
			if ($phrase['root'])
			{
				$ret .= sprintf($template, $this->msg['root_phrase'],
					$this->merge_phrase_list($phrase['root'], 'root_phrase'));
			}
			if ($phrase['roget_class'])
				$ret .= sprintf($template, $this->msg['roget_class'], $phrase['roget_name']);
			if ($phrase['ref_source'])
				$ret .= sprintf($template, $this->msg['ref_source'], $phrase['ref_source_name']);

			// reference
			if ($phrase['reference'])
			{
				$ret .= '<p>' . $this->msg['external_ref']. ':</p>' . LF;
				$ret .= '<ul>' . LF;
				foreach ($phrase['reference'] as $reference)
				{
					$ret .= sprintf(
						'<li><a href="%2$s">%1$s</a></li>' . LF,
						$reference['label'] ? $reference['label'] : $reference['url'],
						$reference['url']
					);
				}
				$ret .= '</ul>' . LF;
			}

			// relation and derivation
			$ret .= $this->show_relation($phrase, 'related_phrase');

			// peribahasa
			if ($phrase['proverbs'])
			{
				$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['proverb']);
				$ret .= '<ul>' . LF;
				foreach ($phrase['proverbs'] as $proverb)
				{
					$ret .= sprintf('<li><em>%1$s</em>: %2$s</li>' . LF, $proverb['proverb'], $proverb['meaning']);
				}
				$ret .= '</ul>' . LF;
			}

			// translation
			if ($phrase['translations'])
			{
				$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['translation']);
				$ret .= '<ul>' . LF;
				foreach ($phrase['translations'] as $translation)
				{
					$ret .= sprintf('<li><em>%1$s</em>: %2$s</li>' . LF, $translation['ref_source'], $translation['translation']);
				}
				$ret .= '</ul>' . LF;
			}

		}
		else
		{
			$ret .= sprintf('<p>%1$s</p>', sprintf($this->msg['phrase_na'], $_GET['phrase']));
			// derivation and relation
			$this->get_relation(&$phrase, 'related_phrase', true);
			$ret .= $this->show_relation($phrase, 'root_phrase');
		}

		// glosarium
		$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['glossary']);
		$_GET['lang'] = 'id';
		$glossary = new glossary(&$this->db, &$this->auth, $this->msg);
		$glossary->sublist = true;
		$ret .= $glossary->show_result();

		$ret .= $this->show_kbbi();

		return($ret);
	}

	/**
	 * @return unknown_type
	 */
	function show_relation($phrase, $col_name)
	{
		// derivation
		$type_count = count($phrase['relation']);
		$col_width = round(100 / $type_count) . '%';
		$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['thesaurus']);
		if ($phrase['relation']['relation_all'] == 0)
		{
			$ret .= '<p>' . $this->msg['nf']. '</p>' . LF;
			return($ret);
		}
		foreach ($phrase['relation'] as $type_key => $type)
		{
			unset($sort1);
			unset($sort2);
			if (count($type) > 1)
			{
				foreach ($type as $key => $row) {
					$sort1[$key]  = $row['lex_class'];
					$sort2[$key]  = $row['related_phrase'];
				}
				array_multisort($sort1, SORT_ASC, $sort2, SORT_ASC, $type);

				$ret .= sprintf('<p><strong>%1$s:</strong></p>' . LF, $type['name']);
				$ret .= '<blockquote>' . LF;
				$ret .= $this->merge_phrase_list($type, $col_name, count($type) - 1, true);
				$ret .= '</blockquote>' . LF;
			}
		}
		return($ret);
	}

	/**
	 * Show form
	 */
	function show_form()
	{
		global $_GET;
		$phrase = $this->get_phrase();
		$is_new = $phrase ? 0 : 1;
		$url = './?mod=dictionary&action=form&phrase=' . ($phrase ? $_GET['phrase'] : '') . '';
		if ($is_new) $phrase['phrase'] = $_GET['phrase'];

		$form = new form('phrase_form', null, $url);
		$form->setup($this->msg);

		// main elements
		$form->addElement('text', 'phrase', $this->msg['phrase'],
			array('size' => 40, 'maxlength' => '255'));
		$form->addElement('select', 'lex_class', $this->msg['lex_class'],
			$this->db->get_row_assoc('SELECT * FROM lexical_class', 'lex_class', 'lex_class_name'));
		$form->addElement('select', 'ref_source', $this->msg['ref_source'],
			$this->db->get_row_assoc('SELECT * FROM ref_source WHERE dictionary = 1', 'ref_source', 'ref_source_name'));
		$form->addElement('select', 'phrase_type', $this->msg['phrase_type'],
			$this->db->get_row_assoc('SELECT * FROM phrase_type ORDER BY sort_order', 'phrase_type', 'phrase_type_name'));
		$form->addElement('select', 'roget_class', $this->msg['roget_class'],
			$this->db->get_row_assoc('SELECT *, CONCAT(roget_class, \' - \', roget_name) roget_class_name FROM roget_class', 'roget_class', 'roget_class_name'));
		$form->addElement('text', 'etymology', $this->msg['etymology'],
			array('size' => 40, 'maxlength' => '255'));
		$form->addElement('text', 'pronounciation', $this->msg['pronounciation'],
			array('size' => 40, 'maxlength' => '255'));
		$form->addElement('text', 'actual_phrase', $this->msg['actual_phrase'],
			array('size' => 40, 'maxlength' => '255'));
		$form->addElement('text', 'info', $this->msg['info'],
			array('size' => 40, 'maxlength' => '255'));
		$form->addElement('text', 'notes', $this->msg['notes'],
			array('size' => 40, 'maxlength' => '255'));
		$form->addElement('submit', 'save', $this->msg['save']);
		$form->addRule('phrase', sprintf($this->msg['required_alert'], $this->msg['phrase']), 'required', null, 'client');
		$form->addRule('phrase_type', sprintf($this->msg['required_alert'], $this->msg['phrase_type']), 'required', null, 'client');
		$form->addRule('lex_class', sprintf($this->msg['required_alert'], $this->msg['lex_class']), 'required', null, 'client');
		$form->setDefaults($phrase);
		$ret .= $form->begin_form();
		$title = !$is_new ? $phrase['phrase'] :
			($_GET['phrase'] ? $_GET['phrase'] : $this->msg['new_flag']);
		$template = '<tr><td>%1$s:</td><td>%2$s</td></tr>' . LF;


		// header
		$ret .= '<script src="js/jquery.js"></script>' . LF;
		$ret .= '<script src="js/common.js"></script>' . LF;
		$ret .= sprintf('<h1>%1$s</h1>' . LF, $title);

		$actions = array(
			'cancel' => array(
				'url' => './?mod=dictionary' . ($is_new ? '' : '&action=view&phrase=' . $_GET['phrase']),
			),
		);
		$ret .= $this->get_action_buttons($actions);

		$ret .= '<table width="100%">' . LF;
		$ret .= '<tr valign="top">' . LF;
		$ret .= '<td width="50%">' . LF;
		$ret .= '<table>' . LF;
		$ret .= sprintf($template, $this->msg['phrase'], $form->get_element('phrase'));
		$ret .= sprintf($template, $this->msg['actual_phrase'], $form->get_element('actual_phrase'));
		$ret .= sprintf($template, $this->msg['phrase_type'], $form->get_element('phrase_type'));
		$ret .= sprintf($template, $this->msg['lex_class'], $form->get_element('lex_class'));
		$ret .= sprintf($template, $this->msg['info'], $form->get_element('info'));
		$ret .= '</table>' . LF;
		$ret .= '</td><td width="50%">' . LF;
		$ret .= '<table>' . LF;
		$ret .= sprintf($template, $this->msg['pronounciation'], $form->get_element('pronounciation'));
		$ret .= sprintf($template, $this->msg['etymology'], $form->get_element('etymology'));
		$ret .= sprintf($template, $this->msg['ref_source'], $form->get_element('ref_source'));
		$ret .= sprintf($template, $this->msg['roget_class'], $form->get_element('roget_class'));
		$ret .= sprintf($template, $this->msg['notes'], $form->get_element('notes'));
		$ret .= '</table>' . LF;
		$ret .= '</td>' . LF;
		$ret .= '</tr>' . LF;
		$ret .= '</table>' . LF;

		// definition
		$ret .= $this->show_sub_form(&$form, &$phrase,
			array(
				'def_uid' => array('type' => 'hidden'),
				'def_num' => array('type' => 'text', 'width' => '1%',
					'option1' => array('size' => 1, 'maxlength' => 5)),
				'lex_class' => array('type' => 'text', 'width' => '1%',
					'option1' => array('size' => 1, 'maxlength' => 5)),
				'discipline' => array('type' => 'text', 'width' => '1%',
					'option1' => array('size' => 5, 'maxlength' => 15)),
				'see' => array('type' => 'text', 'width' => '5%',
					'option1' => array('size' => 8, 'maxlength' => 255)),
				'def_text' => array('type' => 'text', 'width' => '50%',
					'option1' => array('size' => 50, 'maxlength' => 255, 'style' => 'width:100%')),
				'sample' => array('type' => 'text', 'width' => '50%',
					'option1' => array('size' => 50, 'maxlength' => 255, 'style' => 'width:100%')),
				),
			'definition', 'definition', 'definition', 'def_count');

		// reference
		$ret .= $this->show_sub_form(&$form, &$phrase,
			array(
				'ext_uid' => array('type' => 'hidden'),
				'url' => array('type' => 'text', 'width' => '50%',
					'option1' => array('size' => 50, 'maxlength' => 255, 'style' => 'width:100%')),
				'label' => array('type' => 'text', 'width' => '50%',
					'option1' => array('size' => 50, 'maxlength' => 255, 'style' => 'width:100%'))
			),
			'external_ref', 'external_ref', 'reference', 'ext_count');

		// relation
		$ret .= $this->show_sub_form(&$form, &$phrase,
			array(
				'rel_uid' => array('type' => 'hidden'),
				'rel_type' => array('type' => 'select', 'width' => '1%',
					'option1' => $this->db->get_row_assoc('SELECT * FROM relation_type', 'rel_type', 'rel_type_name')),
				'related_phrase' => array('type' => 'text', 'width' => '99%',
					'option1' => array('size' => 50, 'maxlength' => 255, 'style' => 'width:100%'))),
			'relation', 'thesaurus', 'all_relation', 'rel_count');

		// end
		$ret .= '<input name="is_new" type="hidden" value="' . $is_new . '" />' . LF;
		$ret .= sprintf('<p>%1$s</p>', $form->get_element('save'));
		$ret .= $form->end_form();

		// kbbi
		$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg['kbbi_ref']);
		$this->kbbi = new kbbi($this->msg, &$this->db);
		$ret .= $this->kbbi->query($_GET['phrase'], 1) . '</b></i>' . LF;

		//var_dump($form->toArray());
		//die();

		return($ret);
	}

	/**
	 * @return unknown_type
	 */
	function show_sub_form(&$form, &$phrase, $field_def, $name, $heading, $phrase_field, $count_name)
	{
		// definition
		$hidden_field = '';
		$defs = &$phrase[$phrase_field];
		$new_def = $field_def;
		foreach ($new_def as $key => $val) $new_def[$key] = '';
		$defs[] = $new_def;
		$def_count = count($defs);

		foreach ($field_def as $field_key => $field)
			$elms .= ($elms ? ', ' : '') . $field_key;

		// dynamic row
		$ret .= '<script>' . LF;
		$ret .= '$(function(){' . LF;
		$ret .= '$(\'#add_' . $name . '\').click(function() {' . LF;
		$ret .= 'add_row(\'#' . $name . '\', \'' . $elms . '\', \'' . $count_name . '\');' . LF;
		$ret .= '});' . LF;
		$ret .= '});' . LF;
		$ret .= '</script>' . LF;

		// header
		$ret .= sprintf('<h2>%1$s</h2>' . LF, $this->msg[$heading]);
		$ret .= '<p><span id="add_' . $name . '" class="action_button">' . $this->msg['add_row'] . '</span></p>' . LF;
		$ret .= '<table id="' . $name . '" width="100%">' . LF;
		$ret .= '<tr>' . LF;
		foreach ($field_def as $field_key => $field)
		{
			if ($field['type'] != 'hidden')
				$ret .= sprintf('<th width="%2$s" style="background: #DDD;">%1$s</th>' . LF,
					$this->msg[$field_key], $field['width']);
		}
		$ret .= '</tr>' . LF;

		// data
		if ($defs)
		{
			for ($i = 0; $i < $def_count; $i++)
			{
				$def = $defs[$i];
				// hidden fields
				$hidden_field = '';
				foreach ($field_def as $field_key => $field)
				{
					if ($field['type'] == 'hidden')
					{
						$field_name = $field_key . '_' . $i;
						$field['option1']['id'] = $field_name;
						$form->addElement($field['type'], $field_name, $this->msg['number'], $field['option1']);
						$form->setDefaults(array($field_name => $defs[$i][$field_key]));
						$hidden_field .= $form->get_element($field_name) . LF;
					}
				}
				$ret .= '<tr>' . LF;
				// visible fields
				foreach ($field_def as $field_key => $field)
				{
					if ($field['type'] != 'hidden')
					{
						$field_name = $field_key . '_' . $i;
						$field['option1']['id'] = $field_name;
						$form->addElement($field['type'], $field_name, $this->msg['number'], $field['option1']);
						$form->setDefaults(array($field_name => $defs[$i][$field_key]));
						$ret .= '<td width="' . $field['width'] . '">' . LF;
						if ($hidden_field)
						{
							$ret .= $hidden_field;
							$hidden_field = '';
						}
						$ret .= $form->get_element($field_name) . LF;
						$ret .= '</td>' . LF;
					}
				}
				$ret .= '</tr>' . LF;
			}
		}
		$ret .= '</table>' . LF;
		$ret .= '<input name="' . $count_name . '" id="' . $count_name . '" type="hidden" value="' . $def_count . '" />' . LF;
		return($ret);
	}

	/**
	 *
	 */
	function show_search()
	{
		$operators = array(
			'1' => $this->msg['search_1'],
			'2' => $this->msg['search_2'],
			'3' => $this->msg['search_3'],
			'4' => $this->msg['search_4'],
			'5' => $this->msg['search_5'],
		);

		$form = new form('search_dict', 'get');
		$form->setup($msg);
		$form->addElement('hidden', 'mod', 'dictionary');
		$form->addElement('select', 'op', null, $operators);
		$form->addElement('text', 'phrase', $this->msg['phrase']);
		$form->addElement('select', 'lex', $this->msg['lex_class'],
			$this->db->get_row_assoc(
				'SELECT lex_class, lex_class_name FROM lexical_class ORDER BY sort_order',
				'lex_class', 'lex_class_name', $this->msg['all'])
			);
		$form->addElement('select', 'type', $this->msg['phrase_type'],
			$this->db->get_row_assoc(
				'SELECT phrase_type, phrase_type_name FROM phrase_type ORDER BY sort_order',
				'phrase_type', 'phrase_type_name', $this->msg['all'])
			);
		$form->addElement('submit', 'srch', $this->msg['search_button']);

		$template = '<span class="search_param">%1$s: %2$s</span>' . LF;
		$ret .= '<fieldset>' . LF;
		$ret .= '<legend>' . $this->msg['search'] . '</legend>' . LF;
		$ret .= $form->begin_form();
		$ret .= sprintf($template, $this->msg['search_op'], $form->get_element('op'));
		$ret .= sprintf($template, $this->msg['phrase'], $form->get_element('phrase'));
		$ret .= sprintf($template, $this->msg['lex_class'], $form->get_element('lex'));
		$ret .= sprintf($template, $this->msg['phrase_type'], $form->get_element('type'));
		$ret .= $form->get_element('mod');
		$ret .= $form->get_element('srch');
		$ret .= $form->end_form();
		$ret .= '</fieldset>' . LF;

		return($ret);
	}

	/**
	 * Get list of words
	 */
	function get_keywords()
	{
		if ($this->phrase)
		{
			$keywords[] = $this->phrase['phrase'];
			if ($relations = $this->phrase['all_relation'])
			{
				foreach($relations as $relation)
				{
					$keywords[] = $relation['related_phrase'];
				}
			}
		}
		// process keywords
		if ($keywords)
		{
			foreach($keywords as $keyword)
			{
				$ret .= $ret ? ', ' : '';
				$ret .= $keyword;
			}
		}
		return($ret);
	}

	/**
	 * Get list of words
	 */
	function get_list()
	{
		global $_GET;
		$query = 'SELECT COUNT(*) FROM phrase a WHERE a.phrase
			LIKE \'%' . $this->db->quote($_GET['phrase'], null, false) . '%\';';
		$count = $this->db->get_row_value($query);
		$ret = $_GET['phrase'] ? $count : true;
		return($ret);
	}

	/**
	 * Get phrase
	 *
	 * @return Phrase structure
	 */
	function get_phrase($input = null)
	{
		global $_GET;
		$search = $input ? $input : $_GET['phrase'];
		// phrase
		$query = sprintf('
			SELECT a.*, b.lex_class_name, c.roget_name,
				d.ref_source_name, b.lex_class_ref
			FROM phrase a
				LEFT JOIN lexical_class b ON a.lex_class = b.lex_class
				LEFT JOIN roget_class c ON a.roget_class = c.roget_class
				LEFT JOIN ref_source d ON a.ref_source = d.ref_source
			WHERE a.phrase = %1$s;',
			$this->db->quote($search)
		);
//		echo($query);

		$phrase = $this->db->get_row($query);
		if ($phrase)
		{
			$search = $phrase['phrase'];
			// root
			if ($phrase['type'] != 'r' && !$input)
			{
				$query = sprintf('SELECT a.root_phrase, a.rel_type
					FROM relation a
					WHERE a.related_phrase = %1$s AND a.rel_type IN (\'d\', \'c\')
					ORDER BY a.root_phrase',
					$this->db->quote($search)
				);
				$rows = $this->db->get_rows($query);
				$phrase['root'] = $rows;
			}

			// definition
			$query = sprintf('SELECT a.*, c.lex_class_ref
				FROM definition a
					LEFT JOIN lexical_class c ON a.lex_class = c.lex_class
				WHERE a.phrase = %1$s
				ORDER BY a.def_num, a.def_uid',
				$this->db->quote($search), $this->db->quote($class_name)
			);
			$rows = $this->db->get_rows($query);
			$phrase['definition'] = $rows;

			if (!$input)
			{
				// external reference
				$query = sprintf('SELECT a.*
					FROM external_ref a
					WHERE a.phrase = %1$s',
					$this->db->quote($search)
				);
				$rows = $this->db->get_rows($query);
				$phrase['reference'] = $rows;

				// proverb
				$query = sprintf('SELECT a.*
					FROM proverb a
					WHERE a.prv_type = 1 AND a.phrase = %1$s
					ORDER BY a.proverb',
					$this->db->quote($search));
				$rows = $this->db->get_rows($query);
				$phrase['proverbs'] = $rows;

				// translation
				$query = sprintf('SELECT a.*
					FROM translation a
					WHERE a.lemma = %1$s',
					$this->db->quote($search)
				);
				$rows = $this->db->get_rows($query);
				$phrase['translations'] = $rows;

			// opentran
//			$opentran = new opentran();
//			if ($translation = $opentran->translate($search))
//				$phrase['translations'][] = $translation;

			// derivation and relation
				$this->get_relation(&$phrase, 'related_phrase');
			}
		}
		return($phrase);
	}

	/**
	 * @return unknown_type
	 */
	function get_relation(&$phrase, $sort_phrase, $reverse = false)
	{
		global $_GET;
		$where_field = 'root_phrase';
		if ($reverse)
		{
			$temp = $sort_phrase;
			$sort_phrase = $where_field;
			$where_field = $temp;
		}

		// relation
		$query = sprintf('
			SELECT a.*, b.rel_type_name, c.lex_class
			FROM %2$s a
				INNER JOIN relation_type b ON a.rel_type = b.rel_type
				LEFT JOIN phrase c ON a.%4$s = c.phrase
			WHERE a.%5$s = %1$s
			ORDER BY b.sort_order, a.%4$s;',
			$this->db->quote($_GET['phrase']),
			'relation',
			'rel_type',
			$sort_phrase,
			$where_field);
		$rows = $this->db->get_rows($query);
		//echo($query);

		if (!$reverse)
		{
			$query = sprintf('
				SELECT a.related_phrase root_phrase,
					a.root_phrase related_phrase, a.rel_type, b.rel_type_name, c.lex_class
				FROM relation a
					INNER JOIN relation_type b ON a.rel_type = b.rel_type
					LEFT JOIN phrase c ON a.%5$s = c.phrase
				WHERE a.%4$s = %1$s AND a.rel_type IN (\'s\', \'a\', \'r\')
				ORDER BY b.sort_order, a.%4$s;',
				$this->db->quote($_GET['phrase']),
				'relation',
				'rel_type',
				$sort_phrase,
				$where_field);
			$rows2 = $this->db->get_rows($query);
			//die($query);
		}

		// divide into each category
		$phrase['relation']['relation_direct'] = 0;
		$phrase['relation']['relation_reverse'] = 0;
		$query = 'SELECT rel_type, rel_type_name FROM relation_type ORDER BY sort_order;';
		$types = $this->db->get_rows($query);
		foreach ($types as $type)
		{
			$inserted = null;
			$type_key = $type['rel_type'];
			foreach ($rows as $row)
			{
				if ($row['rel_type'] == $type['rel_type'])
				{
					$phrase['relation']['relation_direct']++;
					$phrase['relation'][$type_key][] = $row;
					$inserted[] = $row[$sort_phrase];
				}
			}
			$phrase['relation'][$type_key]['name'] = $type['rel_type_name'];

			// reverse
			if ($rows2)
			{
				foreach ($rows2 as $row)
				{
					if ($row['rel_type'] == $type['rel_type'])
					{
						if (!is_array($inserted)) $inserted = array('');
						if (!in_array($row[$sort_phrase], $inserted))
						{
							$phrase['relation']['relation_reverse']++;
							$phrase['relation'][$type_key][] = $row;
						}
					}
				}
			}
		}
		$phrase['relation']['relation_all'] = $phrase['relation']['relation_direct'];
		$phrase['relation']['relation_all'] += $phrase['relation']['relation_reverse'];

		// bulk
		foreach ($rows as $row)
			$phrase['all_relation'][] = $row;
	}

	/**
	 * Save phrase update
	 *
	 * @return unknown_type
	 */
	function save_form()
	{
		global $_GET, $_POST;
		$is_new = ($_POST['is_new'] == 1);
		$old_key = $_GET['phrase'];
		$new_key = $_POST['phrase'];
		// main
		$query = ($is_new ? 'INSERT INTO' : 'UPDATE') . ' phrase SET ';
		$query .= sprintf('
			phrase = %1$s,
			phrase_type = %2$s,
			lex_class = %3$s,
			pronounciation = %4$s,
			etymology = %5$s,
			ref_source = %6$s,
			roget_class = %7$s,
			info = %8$s,
			notes = %9$s,
			actual_phrase = %10$s,
			updater = %11$s,
			updated = NOW()',
			$this->db->quote($new_key),
			$this->db->quote($_POST['phrase_type']),
			$this->db->quote($_POST['lex_class']),
			$this->db->quote($_POST['pronounciation']),
			$this->db->quote($_POST['etymology']),
			$this->db->quote($_POST['ref_source']),
			$this->db->quote($_POST['roget_class']),
			$this->db->quote($_POST['info']),
			$this->db->quote($_POST['notes']),
			$this->db->quote($_POST['actual_phrase']),
			$this->db->quote($this->auth->getUsername())
		);
		if ($is_new)
			$query .= sprintf(',
				creator = %1$s,
				created = NOW();',
				$this->db->quote($this->auth->getUsername())
			);
		else
			$query .= sprintf(' WHERE phrase = %1$s;',
				$this->db->quote($old_key)
			);
		//die($query);
		$this->db->exec($query);

		// subform
		$this->save_sub_form(
			'definition', 'def_uid', 'def_count', 'phrase',
			array('def_num', 'def_text'),
			array('discipline', 'sample', 'see', 'lex_class')
		);
		$this->save_sub_form(
			'external_ref', 'ext_uid', 'ext_count', 'phrase',
			array('url'),
			array('label')
		);
		$this->save_sub_form(
			'relation', 'rel_uid', 'rel_count', 'root_phrase',
			array('rel_type', 'related_phrase')
		);

		// reverse relation
		if (!$is_new && ($old_key != $new_key))
		{
			$query = sprintf('UPDATE relation
				SET related_phrase = %1$s WHERE related_phrase = %2$s;',
				$this->db->quote($new_key),
				$this->db->quote($old_key)
			);
			$this->db->exec($query);
		}

		// update count
		$query = 'UPDATE phrase a SET a.def_count = (
			SELECT COUNT(b.def_uid) FROM definition b
			WHERE a.phrase = b.phrase)
			WHERE a.phrase = ' . $this->db->quote($new_key) . ';';
		$this->db->exec($query);

		// redirect
		redir('./?mod=dictionary&action=view&phrase=' . $new_key);
	}

	/**
	 * @return unknown_type
	 */
	function save_sub_form($table, $uid, $count_field, $phrase_field, $required, $optional = null)
	{
		global $_GET, $_POST;
		$sub_item = $_POST[$count_field];
		$sub_query = '';
		for ($i = 0; $i < $sub_item; $i++)
		{
			$sql_field = '';
			$sql_value = '';
			$sql_update = '';
			$posted_uid = $uid . '_' . $i;
			// check if any of the fields are empty
			$is_empty = false;
			$fields = $required;
			if ($optional) $fields = array_merge($required, $optional);
			foreach ($fields as $field)
			{
				$value = $_POST[$field . '_' . $i];
				if (!$value && in_array($field, $required)) $is_empty = true;
				$sql_field .= ' , ' . $field;
				$sql_value .= ' , ' . $this->db->quote($value);
				$sql_update .=  ' , ' . $field . ' = ' . $this->db->quote($value);
			}
			$sql_field .= ' , updated, updater';
			$sql_value .= ' , NOW(), ' . $this->db->quote($this->auth->getUsername());
			$sql_update .=  ' , updated = NOW(), updater = ' . $this->db->quote($this->auth->getUsername());
			// if not empty, update or add new
			if (!$is_empty)
			{
				if ($_POST[$posted_uid])
				{
					$sub_query = sprintf(
						'UPDATE %1$s SET %5$s = %3$s %4$s WHERE %6$s = %2$s;',
						$table,
						$this->db->quote($_POST[$posted_uid]),
						$this->db->quote($_POST['phrase']),
						$sql_update,
						$phrase_field,
						$uid
					);
					//echo($sub_query . '<br />');
					$this->db->exec($sub_query);
				}
				else
				{
					$sub_query = sprintf('INSERT INTO %1$s (%3$s %4$s)
						VALUES (%2$s %5$s);',
						$table,
						$this->db->quote($_POST['phrase']),
						$phrase_field,
						$sql_field,
						$sql_value
					);
					//echo($sub_query . '<br />');
					$this->db->exec($sub_query);
				}
			}
			// if empty, delete
			else
			{
				if ($_POST[$posted_uid] != '')
				{
					$sub_query = sprintf('DELETE FROM %1$s WHERE %3$s = %2$s;',
						$table, $this->db->quote($_POST[$posted_uid]), $uid);
					//echo($sub_query . '<br />');
					$this->db->exec($sub_query);
				}
			}
		}
	}

	/**
	 * Merge phrase list with comma
	 */
	function merge_phrase_list($phrases, $col_name, $count = null, $show_lex = false)
	{
		$lex_classes = $this->db->get_row_assoc(
			'SELECT * FROM lexical_class', 'lex_class', 'lex_class_name');
		if (is_null($count)) $count = count($phrases);
		if ($count > 0)
		{
			for ($i = 0; $i < $count; $i++)
			{
				$lex1 = $phrases[$i]['lex_class'];
				// $ret .= ($i == 0) ? '<br />': '';
				if ($show_lex && $phrases[$i]['lex_class'] && ($lex1 != $lex2))
				{
					$ret .= sprintf('<em><span title="%1$s">%2$s</span></em>',
						$lex_classes[$phrases[$i]['lex_class']],
						$phrases[$i]['lex_class']
					);
					$ret .= ': ';
				}
				$ret .= sprintf('<a href="%2$s%1$s">%1$s</a>',
					$phrases[$i][$col_name],
					'./?mod=dictionary&action=view&phrase='
				);
				$ret .= ($i < $count - 1) ? '; ': '';
				$lex2 = $lex1;
			}
		}
		else
		{
			$ret = '';
		}
		return($ret);
	}

	/**
	 * Get abbreviation
	 */
	function get_abbrev($source)
	{
		$abbrevs = $this->db->get_row_assoc(
			'SELECT * FROM sys_abbrev', 'abbrev', 'label');
		$sources = explode(', ', $source);
		if (is_array($sources))
		{
			$count = count($sources);
			for ($i = 0; $i < $count; $i++)
			{
				$src = $sources[$i];
				if ($i > 0) $ret .= ', ';
				if (array_key_exists($src, $abbrevs))
					$ret .= sprintf('<span title="%1$s">%2$s</span>',
						$abbrevs[$src],
						$src
					);
				else
					$ret .= $src;
			}
		}
		else
			$ret = $source;
		return($ret);
	}

	/**
	 * Delete lemma
	 */
	function delete($lemma)
	{
		$queries = array(
			'delete from phrase where phrase = %1$s;',
			'delete from definition where phrase = %1$s;',
			'delete from relation where root_phrase = %1$s;',
			'delete from relation where related_phrase = %1$s;',
		);
		foreach ($queries as $query)
		{
			$query = sprintf($query, $this->db->quote($lemma));
			$this->db->exec($query);
		}
	}

	/**
	 * Show KBBI reference
	 */
	function show_kbbi()
	{
		$ret .= '</td><td width="1%">&nbsp;</td><td width="40%" style="background:#EEE; padding: 10px;">' . LF;
		$ret .= sprintf('<p><strong>%1$s</strong></p>' . LF, $this->msg['kbbi_ref']);
		$ret .= $this->kbbi->query($_GET['phrase'], 1) . '</b></i>' . LF;
		$ret .= '</td></tr></table>' . LF;

		return($ret);
	}

	/**
	 * Save KBBI
	 */
	function save_kbbi($phrase)
	{
		global $is_offline;
		if ($is_offline) return;

		if ($this->kbbi->defs)
		{
			foreach($this->kbbi->defs as $key => $value)
			{
				// phrase
				$query = sprintf(
					'INSERT INTO phrase SET
						phrase = %1$s,
						created = NOW(),
						ref_source = \'Pusba\'
					;',
					$this->db->quote($key)
				);
				$this->db->exec($query);

				// update phrase
				$query = sprintf(
					'UPDATE phrase SET
						lex_class = IFNULL(%2$s, \'l\'),
						phrase_type = IFNULL(%3$s, \'\'),
						pronounciation = %4$s,
						actual_phrase = %5$s,
						info = %6$s,
						updated = NOW(),
						kbbi_updated = NOW(),
						created = NOW(),
						ref_source = \'Pusba\'
					WHERE phrase = %1$s;',
					$this->db->quote($key),
					$this->db->quote($value['lex_class']),
					$this->db->quote($value['type']),
					$this->db->quote($value['pron']),
					$this->db->quote($value['actual']),
					$this->db->quote($value['info'])
				);
				$this->db->exec($query);
				//die($query . '<br />');

				// delete relation. use when needed
//				$query = sprintf(
//					'DELETE FROM phrase WHERE phrase IN
//						(SELECT related_phrase FROM relation WHERE root_phrase = %1$s);',
//					$this->db->quote($key)
//				);
//				$this->db->exec($query);
//				$query = sprintf(
//					'DELETE FROM definition WHERE phrase IN
//						(SELECT related_phrase FROM relation WHERE root_phrase = %1$s);',
//					$this->db->quote($key)
//				);
//				$this->db->exec($query);
				$query = sprintf(
					'DELETE FROM relation WHERE root_phrase = %1$s;',
					$this->db->quote($key)
				);
				$this->db->exec($query);

				// relation
				if ($value['type'] != 'r')
				{
					$query = sprintf(
						'INSERT INTO relation (root_phrase, related_phrase, rel_type)
							VALUES (%1$s, %2$s, %3$s);',
						$this->db->quote($phrase),
						$this->db->quote($key),
						$this->db->quote($value['type'])
					);
					$this->db->exec($query);
				}
				$this->db->exec($query);

				// delete definition. use when needed
				$query = sprintf(
					'DELETE FROM definition WHERE phrase = %1$s;',
					$this->db->quote($key)
				);
				$this->db->exec($query);

				// definitions
				if ($value['definitions'])
				{
					foreach ($value['definitions'] as $def_key => $def_val)
					{
						$query = sprintf(
							'INSERT INTO definition (phrase, def_num, lex_class, discipline, def_text, sample, see)
								VALUES (%1$s, %2$s, %3$s, %4$s, %5$s, %6$s, %7$s);',
							$this->db->quote($key),
							$this->db->quote($def_val['index']),
							$this->db->quote($def_val['lex_class']),
							$this->db->quote($def_val['info']),
							$this->db->quote($def_val['text']),
							$this->db->quote($def_val['sample']),
							$this->db->quote($def_val['see'])
						);
						$this->db->exec($query);
					}
				}

				// synonyms
				if ($value['synonyms'])
				{
//					var_dump($value['synonyms']);
					foreach ($value['synonyms'] as $synonym)
					{
						$query = sprintf(
							'INSERT INTO relation (root_phrase, related_phrase, rel_type)
								VALUES (%1$s, %2$s, \'s\');',
							$this->db->quote($key),
							$this->db->quote($synonym)
						);
						$this->db->exec($query);
					}
				}

				// update count
				$query = 'UPDATE phrase a SET a.def_count = (
					SELECT COUNT(b.def_uid) FROM definition b
					WHERE a.phrase = b.phrase)
					WHERE a.phrase = ' . $this->db->quote($key) . ';';
				//echo($query . '<br />');
				$this->db->exec($query);

			}
		}

	}

	/**
	 * Save KBBI (selected)
	 */
	function save_kbbi2($phrase)
	{
		global $is_offline;
		if ($is_offline) return;

		if ($this->kbbi->defs)
		{
			foreach($this->kbbi->defs as $key => $value)
			{
				// update phrase
				$query = sprintf(
					'UPDATE phrase SET
						info = %2$s,
						kbbi_updated = NOW()
					WHERE phrase = %1$s;',
					$this->db->quote($key),
					$this->db->quote($value['info'])
				);
				$this->db->exec($query);
			}
		}
	}

	/**
	 * Get API
	 */
	function getAPI()
	{
		return($this->get_phrase());
	}

	/**
	 * Experiment
	 */
	function show_phrase_brief()
	{
		$phrase = $this->get_phrase();
		$def_count = count($phrase['definition']);
		$redirect = $phrase['actual_phrase'];
		$ret .= '<p>';
		// redirect
		if ($redirect)
		{
			$ret .= sprintf('<b>%1$s</b> &#8594; <a href="%3$s%2$s">%2$s</a>',
				$phrase['phrase'],
				$phrase['actual_phrase'],
				'./?mod=dictionary&action=view&phrase='
			);
			$ret .= '</p>' . LF;
			return($ret);
		}
		// header
		$ret .= sprintf('<b>%1$s</b>%2$s%3$s%4$s',
			$phrase['phrase'],
			$phrase['pronounciation'] ? ' /' . $phrase['pronounciation'] . '/' : '',
			$phrase['lex_class'] ? ' <i>' . $phrase['lex_class'] . '</i>' : '',
			$phrase['discipline'] ? ' <i>(' . $phrase['discipline'] . '</i>)' : ''
		);
		// definitions
		foreach ($phrase['definition'] as $idx => $def)
		{
			$ret .= sprintf('%1$s%2$s%3$s%4$s %5$s%6$s',
				$idx >= 1 ? '; ' : '',
				$def_count > 1 ? ' <b>' . ($idx + 1) . '</b>' : '',
				$def['lex_class'] ? ' <i>' . $def['lex_class'] . '</i>' : '',
				$def['discipline'] ? ' <i>' . $def['discipline'] . '</i>' : '',
				$def['def_text'],
				$def['sample'] ? ': <i>' . $def['sample'] . '</i> ' : ''
			);
		}
		// source
		if ($phrase['ref_source']) $ret .= sprintf(' (%1$s)', $phrase['ref_source_name']);
		$ret .= '</p>' . LF;
		if ($phrase['etymology']) $ret .= sprintf('<blockquote>Etimologi: %1$s</blockquote>', $phrase['etymology']);
		if ($phrase['notes']) $ret .= sprintf('<blockquote>Catatan: %1$s</blockquote>', $phrase['notes']);
		// root
		if ($phrase['root'])
		{
			$ret .= '<blockquote>';
			$ret .= 'Kata dasar:';
			foreach ($phrase['root'] as $idx => $root)
			{
				$ret .= sprintf('%2$s <a href="%3$s%1$s">%1$s</a>%4$s',
					$root['root_phrase'],
					$idx >= 1 ? '; ' : '',
					'./?mod=dictionary&action=view&phrase=',
					$root['lex_class'] ? ' (<i>' . $root['lex_class'] . '</i>)' : '');
			}
			$ret .= '</blockquote>' . LF;
		}
		// relations
		foreach ($phrase['relation'] as $key => $rel)
		{
			if (count($rel) > 1)
			{
				$ret .= '<blockquote>';
				if ($rel['name'] != 'Turunan')
				{
					$ret .= sprintf('%1$s: ', $rel['name']);
					foreach ($rel as $rel_idx => $rel_phrase)
					{
						if (is_numeric($rel_idx))
						{
	//						$ret .= sprintf('%2$s <a href="%3$s%1$s">%1$s</a>%4$s',
	//							$rel_phrase['related_phrase'],
	//							$rel_idx >= 1 ? '; ' : '',
	//							'./?mod=dictionary&action=view&phrase=',
	//							$rel_phrase['lex_class'] ? ' (<i>' . $rel_phrase['lex_class'] . '</i>)' : '');
							$ret .= sprintf('%2$s <a href="%3$s%1$s">%1$s</a>',
								$rel_phrase['related_phrase'],
								$rel_idx >= 1 ? '; ' : '',
								'./?mod=dictionary&action=view&phrase=');
						}
					}
				}
				else
				{
					foreach ($rel as $rel_idx => $rel_phrase)
					{
						if (is_numeric($rel_idx))
						{
							$ret .= '<p>';
							$rel_data = $this->get_phrase($rel_phrase['related_phrase']);
							$ret .= sprintf('<b><a href="%2$s%1$s">%1$s</a></b>',
								$rel_phrase['related_phrase'],
								'./?mod=dictionary&action=view&phrase='
							);
							foreach ($rel_data['definition'] as $idx => $def)
							{
								$ret .= sprintf('%1$s%2$s%3$s%4$s %5$s%6$s',
									$idx >= 1 ? '; ' : '',
									count($rel_data['definition']) > 1 ? ' <b>' . ($idx + 1) . '</b>' : '',
									$def['lex_class'] ? ' <i>' . $def['lex_class'] . '</i>' : '',
									$def['discipline'] ? ' <i>' . $def['discipline'] . '</i>' : '',
									$def['def_text'],
									$def['sample'] ? ': <i>' . $def['sample'] . '</i> ' : ''
								);
							}
							$ret .= '</p>' . LF;
						}
					}
				}
				$ret .= '</blockquote>' . LF;
			}
		}
		// peribahasa
		if ($phrase['proverbs'])
		{
			$ret .= '<blockquote>';
			$ret .= 'Peribahasa: ';
			foreach ($phrase['proverbs'] as $idx => $proverb)
			{
				$ret .= sprintf('%3$s%4$s <em>%1$s</em>: %2$s',
					$proverb['proverb'],
					$proverb['meaning'],
					$idx >= 1 ? '; ' : '',
					count($phrase['proverbs']) > 1 ? '<b>' . ($idx + 1) . '</b>' : '');
			}
			$ret .= '</blockquote>' . LF;
		}
		// translation
		if ($phrase['translations'])
		{
			$ret .= '<blockquote>';
			$ret .= 'Terjemahan: ';
			foreach ($phrase['translations'] as $idx => $translation)
			{
				$ret .= sprintf('%3$s<b>%1$s</b> %2$s',
					$translation['ref_source'],
					$translation['translation'],
					$idx >= 1 ? '; ' : '');
			}
			$ret .= '</blockquote>' . LF;
		}
		// reference
		if ($phrase['reference'])
		{
			$ret .= '<blockquote>';
			$ret .= 'Rujukan: ';
			foreach ($phrase['reference'] as $idx => $ref)
			{
				$ret .= sprintf('%3$s%4$s <a href="%2$s">%1$s</a>',
					$ref['label'] ? $ref['label'] : $ref['url'],
					$ref['url'],
					$idx >= 1 ? '; ' : '',
					count($phrase['reference']) > 1 ? '<b>' . ($idx + 1) . '</b>' : '');
			}
			$ret .= '</blockquote>' . LF;
		}

		$this->kbbi = new kbbi($this->msg, &$this->db);
		$ret .= $this->kbbi->query($phrase['phrase'], 1) . '</b></i>' . LF;

		return($ret);
	}

	/**
	 * Return prev next navigation
	 */
	function get_prev_next($id)
	{
		$limit = 5;
		$table = 'phrase';
		$field = 'phrase';
		// prepare and execute sql
		$tpl = 'SELECT %1$s FROM %2$s WHERE %1$s <ops> %3$s ORDER BY %1$s <sort> LIMIT %4$s;';
		$tpl = sprintf($tpl, $field, $table, $this->db->quote($id), $limit);
		$tpl = str_replace('<sort>', '%2$s', str_replace('<ops>', '%1$s', $tpl));
		$prev = $this->db->get_rows(sprintf($tpl, '<', 'DESC'), false);
		$next = $this->db->get_rows(sprintf($tpl, '>', 'ASC'), false);
		// populate and sort array
		if (is_array($prev)) foreach ($prev as $val) $arr[strtolower($val[0])] = $val[0];
		$arr[strtolower($id)] = $id;
		if (is_array($next)) foreach ($next as $val) $arr[strtolower($val[0])] = $val[0];
		// create html
		if (is_array($arr))
		{
			ksort($arr);
			foreach ($arr as $val)
			{
				$ret .= $ret ? ' ~ ' : '';
				if ($val == $id)
					$ret .= $id;
				else
					$ret .= sprintf('<a href="%2$s%1$s">%1$s</a>',
						$val, './?mod=dictionary&action=view&phrase=');
			}
		}
		return($ret);
	}
};
?>