<?php
/**
 *
 *
 *
 */
class comment extends page
{
	var $db;
	var $auth;
	var $msg;
	var $title;
	var $status = 0; // 0=normal; 1=post

	/**
	 * Constructor
	 */
	function comment(&$db, &$auth, $msg)
	{
		$this->db = $db;
		$this->auth = $auth;
		$this->msg = $msg;
	}

	/**
	 *
	 */
	function process()
	{
		global $_SERVER;
		$is_post = ($_SERVER['REQUEST_METHOD'] == 'POST');
		if ($is_post)
		{
			$this->save_form();
			$this->status = 1;
		}
	}

	/**
	 *
	 */
	function show()
	{
		global $_GET;
		$ret .= sprintf('<h1>%1$s</h1>' . LF, $this->msg['comment']);
		if ($_GET['action'] == 'view')
			$ret .= $this->show_list();
		else
			$ret .= $this->show_form();
		return($ret);
	}


	/**
	 *
	 */
	function show_list()
	{
		$cols = 'sender_name, sender_email, comment_text';
		$from = 'FROM sys_comment
			ORDER BY comment_id DESC';
		$rows = $this->db->get_rows_paged($cols, $from);
		if ($this->db->num_rows > 0)
		{
			$ret .= '<p>' . $this->db->get_page_nav() . '</p>' . LF;
			foreach ($rows as $row)
			{
				$ret .= '<p><b>' . $row['sender_name'] . '</b></p>' . LF;
				$ret .= '<p>' . LF;
				$ret .= nl2br(strip_tags($row['comment_text'])) . LF;
				$ret .= '</p>' . LF;
			}
		}
		else
			$ret .= '<p>' . $this->msg['na'] . '</p>' . LF;

		return($ret);
	}

	/**
	 *
	 */
	function show_form()
	{
		$form = new form('entry_form', null, './?mod=comment');
		$form->setup($this->msg);

		$form->addElement('text', 'sender_name', $this->msg['comment_sender'], array('size' => 40, 'maxlength' => '255'));
		$form->addElement('text', 'sender_email', $this->msg['comment_email'], array('size' => 40, 'maxlength' => '255'));
		$form->addElement('textarea', 'comment_text', $this->msg['comment_text'], array('rows' => 10, 'style' => 'width: 100%'));
		$form->addElement('submit', 'save', $this->msg['submit']);
		$form->addRule('sender_name', sprintf($this->msg['required_alert'], $this->msg['comment_sender']), 'required', null, 'client');
		$form->addRule('sender_email', sprintf($this->msg['required_alert'], $this->msg['comment_email']), 'required', null, 'client');
		$form->addRule('sender_email', $this->msg['email_invalid'], 'email', null, 'client');
		$form->addRule('comment_text', sprintf($this->msg['required_alert'], $this->msg['comment_text']), 'required', null, 'client');

		$msg = $this->msg[($this->status == 0 ? 'comment_welcome' : 'comment_sent')];
		$ret .= sprintf('<p>%1$s</p>' . LF, $msg);
		if ($this->status == 0) $ret .= $form->toHtml();
		return($ret);
	}

	/**
	 * Save data
	 */
	function save_form()
	{
		global $_POST;
		$query = sprintf('INSERT INTO sys_comment SET
			sender_name = %1$s,
			sender_email = %2$s,
			comment_text = %3$s,
			ses_id = %4$s
			;',
			$this->db->quote($_POST['sender_name']),
			$this->db->quote($_POST['sender_email']),
			$this->db->quote($_POST['comment_text']),
			$this->db->quote(session_id())
		);
		//die($query);
		$this->db->exec($query);
	}

};
?>