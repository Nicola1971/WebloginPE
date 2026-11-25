<?php
/**
 * WebLoginPE
 * v1.4.1
 *
 * @copyright ©2007-2008 Scotty Delicious, 2026 Nicola Lambathakis
 * @license GPL
 */
class WebLoginPE
{
	/**
	 * An array of language specific phrases.
	 *
	 * @var array
	 * @access public
	 * @see __construct()
	 */
	var $LanguageArray;
	
	/**
	 * Holds a message if one was generated.
	 *
	 * @var string
	 * @access public
	 * @see FormatMessage()
	 */
	var $Report;
	
	/**
	 * A comma separated list of MODx document IDs to attempt to redirect the user to after login.
	 *
	 * @var string
	 * @access public
	 * @see login()
	 * @see LoginHomePage()
	 */
	var $liHomeId;
	
	/**
	 * The MODx document ID to redirect the user to after logout.
	 *
	 * @var string
	 * @access public
	 * @see logout()
	 * @see LogoutHomePage()
	 */
	var $loHomeId;
	
	/**
	 * the type of WebLoginPE (simple, register, profile, users or manager)
	 *
	 * @var string
	 * @access protected
	 */
	var $Type;
	
	/**
	 * Value of $_POST['username'].
	 *
	 * @var string
	 * @access protected
	 */
	var $Username;
	
	/**
	 * Value of $_POST['password'].
	 *
	 * @var string
	 * @access protected
	 */
	var $Password;
	
	/**
	 * The user object assembled from data queried from web_users and web_user_attributes tables
	 *
	 * @var array
	 * @access protected
	 * @see QueryDbForUser()
	 */
	var $User;
	
	/**
	 * Dimensions for the user image
	 *
	 * @var string
	 * @access protected
	 * @see CreateUserImage
	 */
	var $UserImageSettings;
	
	/**
	 * Template for messages returned by WebLoginPE
	 *
	 * @var string;
	 * @access public
	 * @see FormatMessage;
	 */
	var $MessageTemplate;
	
	/**
	 * Number of failed logins
	 *
	 * @var string
	 * @access protected
	 * @see Authenticate
	 */
	var $LoginErrorCount;
	
	/**
	 * Full table name of the custom extended user attributes table.
	 *
	 * @var string
	 * @access protected
	 * @see CustomTable
	 */
	var $CustomTable;
	
	/**
	 * An array of column names for the extended user attributes table.
	 *
	 * @var array
	 * @access protected
	 * @see CustomTable
	 */
	var $CustomFields;
	
	/**
	 * PHP strftime() format for dates in placeholders
	 *
	 * @var string
	 * @access protected
	 * @see PlaceHolders
	 */
	var $DateFormat;
	
	/**
	* MODxMailer
	*/
	var $mailer;
	
	/**
	 * Campi definiti come checkbox in &inputHandler (vedi FieldSubmitted)
	 *
	 * @var array
	 */
	var $CheckboxFields = array();
	
	/**
	 * 1.4.0: upload della foto profilo abilitato (&allowPhoto, default disattivato)
	 * @var bool
	 */
	var $AllowPhoto = false;
	
	/**
	 * 1.4.0: durata in giorni del cookie "ricordami" (&rememberDays, default 30)
	 * @var int
	 */
	var $RememberDays = 30;
	
	/**
	 * 1.4.0: formato della data di nascita (&dobFormat, default m-d-Y)
	 * @var string
	 */
	var $DobFormat = 'm-d-Y';
	
	/**
	 * 1.4.0: password salvate con password_hash() (bcrypt) invece di MD5 (&passwordHash=`bcrypt`)
	 * @var bool
	 */
	var $ModernHash = false;
	
	/**
	 * 1.4.0: verifica del token CSRF sui form (&csrf=`1`)
	 * @var bool
	 */
	var $Csrf = false;
	
	/**
	 * 1.4.0: login automatico da token "ricordami" gia' verificato
	 * @var bool
	 */
	var $TokenLogin = false;
	
	/**
	 * 1.4.0: scadenza del token "ricordami" da rinnovare dopo un login automatico
	 * @var int
	 */
	var $RememberExpires = 0;
	
	/**
	 * 1.4.0: lunghezza delle colonne gia' lette (vedi ColumnLength)
	 * @var array
	 */
	var $ColumnCache = array();
	
	/**
	 * 1.4.0: nome del cookie "ricordami". Diverso da "WebLoginPE", che e' usato
	 * anche da MODxAPI/FormLister per il proprio login persistente.
	 * @var string
	 */
	var $CookieName = 'wlpe_remember';
	
	/**
	 * WebLoginPE Class Constructor
	 *
	 * @param array $LanguageArray An array of language specific strings.
	 * @return void
	 * @author Scotty Delicious
	 */
	function __construct($LanguageArray, $dateFormat = '%A %B %d, %Y at %I:%M %p', $UserImageSettings = '105000,100,100', $type = 'simple')
	{
		global $modx;
		$modx->loadExtension('MODxMailer');
		$this->mailer = $modx->mail;
		$this->mailer->Sender = '';
		$this->LanguageArray = $LanguageArray;
		$this->DateFormat = $dateFormat;
		$this->UserImageSettings = $UserImageSettings;
		$this->Type = $type;
	}
	
	
	/**
	 * Reference to the construct method (for PHP4 compatibility)
	 *
	 * @see __construct
	 */
	function WebLoginPE($LanguageArray, $dateFormat = '%A %B %d, %Y at %I:%M %p', $UserImageSettings = '105000,100,100', $type = 'simple')
	{
		$this->__construct($LanguageArray, $dateFormat, $UserImageSettings, $type);
	}
	
	
	/**
	 * FormatMessage
	 * Sets a value for $this->Report which is returned to the page if there is an error.
	 * This function is public and can be used to format a message for the calling script.
	 *
	 * @param string $message 
	 * @return void
	 * @author Scotty Delicious
	 */
	function FormatMessage($message = 'There was an error')
	{
		global $modx;
		
		unset($this->Report);
		$messageTemplate = str_replace('[+wlpe.message.text+]', $message, ((string)$this->MessageTemplate !== '') ? $this->MessageTemplate : '[+wlpe.message.text+]'); // FIX PHP 8.1: template non ancora impostato
		$this->Report = $messageTemplate;
		$modx->setPlaceholder('wlpe.message', $messageTemplate);
		unset ($messageTemplate);
		return;
	}
	
	
	/**
	 * login
	 * Perform all the necessary functions to establish a secure user session with permissions
	 *
	 * @param string $type Type of WebLoginPE call.
	 * @param string $liHomeId Comma separated list of MODx document ID's to attempt to redirect to after login.
	 * @return void
	 * @author Scotty Delicious
	 */
	function Login($type, $liHomeId = '')
	{
		global $modx;
		
		$this->Type = $type;
		$this->liHomeId = $liHomeId;
		
		$this->Username = $modx->db->escape(strip_tags($this->PostString('username')));
		$this->Password = $this->PostString('password'); // 1.4.0: usata solo per la verifica dell'hash, mai nelle query
		if ($this->Username == '' || $this->Password == '')
		{
			$this->FormatMessage($this->LanguageArray[5]);
			return;
		}
		$_SESSION['groups'] = array('Registered Users', 'Fans');
		$this->OnBeforeWebLogin();
		$this->User = $this->QueryDbForUser($this->Username);

		if ($this->User == false)
		{
			$this->FormatMessage($this->LanguageArray[21]);
			return;
		}
		else
		{
			$this->Username = $this->User['username']; // (TS) Can login in any case, but ensure case is correct in session
		}
		
		$this->UserIsBlocked();
		$this->Authenticate();
		$this->SessionHandler('start');
		$this->OnWebLogin();
		$this->ActiveUsers();
		$this->UserDocumentGroups();
		$this->LoginHomePage();
	}
	
	
	/**
	 * AutoLogin checks for a user cookie and logs the user in automatically
	 *
	 * @return void
	 * @author Scotty Delicious
	 */
	function AutoLogin()
	{
		global $modx;
		
		// 1.4.0: login persistente con token casuale (vedi CreateRememberToken)
		if (empty($_COOKIE[$this->CookieName]))
		{
			return false;
		}
		list($selector, $validator) = $this->RememberCookieParts();
		if ($selector === '')
		{
			// cookie non valido
			$this->ClearLoginCookie();
			return false;
		}
		
		$settings = $modx->getFullTableName('web_user_settings');
		$rs = $modx->db->query("SELECT `webuser`, `setting_value` FROM ".$settings." WHERE `setting_name` = 'wlpe_rt_".$selector."'");
		$row = $rs ? $modx->db->getRow($rs) : false;
		if (!is_array($row))
		{
			$this->ClearLoginCookie();
			return false;
		}
		list($expires, $hash) = array_pad(explode(':', (string)$row['setting_value'], 2), 2, '');
		// il token usato viene sempre eliminato: se valido ne viene emesso uno nuovo
		$modx->db->query("DELETE FROM ".$settings." WHERE `setting_name` = 'wlpe_rt_".$selector."'");
		if ((int)$expires < time() || $hash === '' || !hash_equals($hash, hash('sha256', $validator)))
		{
			$this->ClearLoginCookie();
			return false;
		}
		
		$userRs = $modx->db->query("SELECT `username` FROM ".$modx->getFullTableName('web_users')." WHERE `id` = ".(int)$row['webuser']);
		$userRow = $userRs ? $modx->db->getRow($userRs) : false;
		$this->User = is_array($userRow) ? $this->QueryDbForUser($userRow['username']) : false;
		if (!is_array($this->User))
		{
			$this->ClearLoginCookie();
			return false;
		}
		$this->Username = $this->User['username'];
		$this->Password = '';
		$this->TokenLogin = true;
		$this->RememberExpires = (int)$expires;
		
		$this->UserIsBlocked();
		$this->Authenticate();
		if (!empty($this->Report))
		{
			$this->ClearLoginCookie();
			return false;
		}
		$this->SessionHandler('start');
		$this->UserDocumentGroups();
		$this->LoginHomePage();
	}
	
	
	/**
	 * logout
	 * Destroy the user session and redirect or refresh.
	 *
	 * @param string $type Type of WebLoginPE call.
	 * @param int $loHomeId MODx document ID to redirect to after logout.
	 * @return void
	 * @author Scotty Delicious
	 */
	function Logout($type, $loHomeId = '')
	{
		$logoutparameters = array(
				'username'		=> (isset($_SESSION['webShortname']) ? $_SESSION['webShortname'] : ''),
				'internalKey'		=> (isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : ''),
				'userid'		=> (isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : '') // Bugfix by TS
				); // SMF connector fix http://modxcms.com/forums/index.php?topic=26565.0 c/o tazzydemon
				
		$this->Type = $type;
		$this->loHomeId = $loHomeId;
		
		$this->OnBeforeWebLogout(); // TS Bug fix
		$this->StatusToOffline();
		$this->SessionHandler('destroy');
		$this->OnWebLogout($logoutparameters); // SMF connector fix http://modxcms.com/forums/index.php?topic=26565.0 c/o tazzydemon and binary_trust
		$this->LogoutHomePage();
	}
	
	
	/**
	 * Custom table checks for the specified extended user attributes table and creates it if it does not exist.
	 * It also checks for custom column names and inserts them into the extended user attributes table if they do not exist.
	 *
	 * @param string $table The name of the custom table (Default is "web_user_attributes_extended")
	 * @param string $fields A comma separated list of column names for the custom table.
	 * @return void
	 * @author Scotty Delicious
	 */
	function CustomTable($table, $fields, $prefixTable = 1, $tableCheck = 1)
	{
		global $modx;
		
		$allTables = array();
		
		if ($prefixTable == 0)
		{
			$tableFull = '`'.$table.'`';
			$table = $table;
		}
		else
		{
			$tableFull = $modx->getFullTableName($table);
			$table = explode('.', $tableFull);
			$table = str_replace('`', '', $table[1]);
		}
		
		if ($fields !== '')
		{
			$fields = explode(',', str_replace(', ', ',', $fields));
		}
		
		$this->CustomTable = $tableFull;
		$this->CustomFields = $fields;
		
		if ($tableCheck == 1)
		{
			// Check if custom table exists. If it does not, create it with default values.
			$tableNames = $modx->db->query("SHOW TABLES");
			while ($eachTable = $modx->db->getRow($tableNames, 'num'))
	 		{
	 			$allTables[] = $eachTable[0];
	 		}
	 		if (!in_array($table, $allTables))
	 		{
				$createTable = $modx->db->query("CREATE TABLE IF NOT EXISTS ".$this->CustomTable." (id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, internalKey INT(10) NOT NULL DEFAULT 0)");
				if (!$createTable)
				{
					return $this->FormatMessage($modx->db->getLastError());
				}
				$addIndex = $modx->db->query("ALTER TABLE ".$this->CustomTable." ADD INDEX `userid` ( `internalKey` )");
	 		}
			
			// Create the additional MODx events if they do not exist
			$system_eventnames = $modx->getFullTableName('system_eventnames');
			$newEvents = array('OnBeforeWebSaveUser', 'OnBeforeAddToGroup', 'OnViewUserProfile');
			foreach ($newEvents as $aNewEvent)
			{
				$findEvent = $modx->db->query("SELECT * FROM ".$system_eventnames." WHERE `name` = '".$aNewEvent."'");
				$limit = $modx->db->getRecordCount($findEvent);
				if ($limit == 0)
				{
					$addEvent = $modx->db->query("INSERT INTO ".$system_eventnames." (`name`,`service`) VALUES ('".$aNewEvent."', 3)");
				}
			}
		}
		
		// Check if custom fields exist in custom table. If they do not, create them.
		if ($this->CustomFields !== '')
		{
			$columns = $modx->db->query("SELECT * FROM ".$this->CustomTable);
			$columnNames = $modx->db->getColumnNames($columns);
			foreach ($this->CustomFields as $field)
			{
				if (!in_array($field, $columnNames))
				{
					$addColumn = $modx->db->query("ALTER TABLE ".$this->CustomTable." ADD (`".$field."` VARCHAR(255) NOT NULL DEFAULT '')");
				}
			}
		}		
	}
	
	
	/**
	 * register
	 * Inserts a new user into web_users and web_user_attributes.
	 *
	 * @param string $regType 'instant' or 'verify'
	 * @param string $groups which webgroup('s) should the new user be added to.
	 * @param string $regRequired Comma separated list of required fields.
	 * @param string $notify Comma separated list of emails to notify of new registrations.
	 * @param string $notifyTpl Template for email notification message.
	 * @param string $notifySubject Subject line for email notification.
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function Register($regType, $groups, $regRequired, $notify, $notifyTpl, $notifySubject)
	{
		global $modx;
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		$web_groups = $modx->getFullTableName('web_groups');
		$webgroup_names = $modx->getFullTableName('webgroup_names');
		
		$username = $this->PostString('username');
		$this->Username = $username;
	    $password = $this->PostString('password');
		$passwordConfirm = $this->PostString('password_confirm');
	    $fullname = $modx->db->escape($modx->stripTags($this->PostString('fullname')));
	    $email = $modx->db->escape($modx->stripTags($this->PostString('email')));
	    $phone = $modx->db->escape($modx->stripTags($this->PostString('phone')));
		$mobilephone = $modx->db->escape($modx->stripTags($this->PostString('mobilephone')));
		$fax = $modx->db->escape($modx->stripTags($this->PostString('fax')));
		// FIX strict mode: dob e gender sono colonne INT, mai stringa vuota
		$dob = $this->DobToInt(isset($_POST['dob']) ? $_POST['dob'] : '');
		$gender = isset($_POST['gender']) ? (int)$_POST['gender'] : 0;
	    $country = $modx->db->escape($modx->stripTags($this->PostString('country')));
	    $state = $modx->db->escape($modx->stripTags($this->PostString('state')));
	    $zip = $modx->db->escape($modx->stripTags($this->PostString('zip')));
		$comment = $modx->db->escape($modx->stripTags($this->PostString('comment')));
		$cachepwd = time();
		
		// Check for required fields.
if (trim($username) == '') // pixelchutes
		{			
			return $this->FormatMessage($this->LanguageArray[0]);
		}
		if ( strlen($this->PostString('email')) > 0 ) // pixelchutes
		{
			// Validate the email address.
			$this->ValidateEmail($this->PostString('email'));
			if (!empty($this->Report))
			{
				return $this->Report; // FIX: era $this->report (proprieta' inesistente)
			}
		}
		if ($regRequired !== '')
		{
			$requiredFields = explode(',', str_replace(' ,', ',', $regRequired));
			foreach ($requiredFields as $field)
			{
				if ($field == 'formcode')
				{
					$formcode = $this->PostString('formcode');
					if (!isset($_SESSION['veriword']) || $_SESSION['veriword'] !== $formcode)
					{
						return $this->FormatMessage($this->LanguageArray[6]);
					}
				}
				
				if ($field == 'email')
				{
					// Validate the email address.
					$this->ValidateEmail($this->PostString('email'));
					if (!empty($this->Report))
					{
						return $this->Report; // FIX: era $this->report (proprieta' inesistente)
					}
				}
				
				if (empty($_POST[$field]))
				{
					if ($field == 'tos')
					{
						return $this->FormatMessage($this->LanguageArray[34]);
					}
					
					return $this->FormatMessage($this->LanguageArray[0]);
				}
			}
		}
		
		// Check username for bullshit.
		if (!ctype_alnum($username)) // (TS)
		{
			return $this->FormatMessage($this->LanguageArray[32]);
		}
		
		// Check username length 
		if (strlen($username) > 100)
		{
			return $this->FormatMessage($this->LanguageArray[1]);
		}
		
		$fieldMessage = ''; // FIX PHP 8: variabile non inizializzata
		// Check for arrays and that "confirm" fields match.
		foreach ($_POST as $field => $value)
		{
			if (is_array($_POST[$field]))
		
			{
				$_POST[$field] = implode('||', $_POST[$field]);
			}
			
			$confirm = $field.'_confirm';
			if (isset($_POST[$confirm]))
			{
				if ($_POST[$field] !== $_POST[$confirm])
				{
					$error = $this->LanguageArray[2].' <br />';
					$fieldMessage .= str_replace('[+000+]', '"'.$field.'"', $error);
				}
			}
		}
		
		// If confirm fields were mismatched, throw this error:
		if (!empty($fieldMessage))
		{
			$err = $fieldMessage;
			unset($fieldMessage);
			return $this->FormatMessage($err);
		}
		
		// Check Password locally
		if ($regType == 'instant')
		{
			if (strlen($password) < 6)
			{
				return $this->FormatMessage($this->LanguageArray[3]);
			}
			
			if (empty($password) || $password == '')
			{
				return $this->FormatMessage($this->LanguageArray[3]);
			}
			
		}
		
		$checkUsername = $modx->db->query("SELECT `id` FROM ".$web_users." WHERE `username`='".$username."'");
		$limit = $modx->recordCount($checkUsername);
		
		if ($limit > 0)
		{
			return $this->FormatMessage($this->LanguageArray[7]);
		}
		
		$lowercase = strtolower(str_replace(' ', '_', $username));
		if ($lowercase == 'default_user')
		{
			return $this->FormatMessage($this->LanguageArray[7]);
		}
		
		$checkEmail = $modx->db->query("SELECT * FROM ".$web_user_attributes." WHERE `email`='".$email."'");
		$limit = $modx->recordCount($checkEmail);
		
		if ($limit > 0)
		{
			return $this->FormatMessage($this->LanguageArray[8]);
		}
		
		// If you want to verify your users email address before letting them log in, this generates a random password.
		if ($regType == 'verify')
		{
			$password = $this->GeneratePassword(10);
		}
		
		// Create the user image if necessary.
		if ($this->AllowPhoto && !empty($_FILES['photo']['name'])) // 1.4.0: upload solo con &allowPhoto
		{
			$photo = $this->CreateUserImage();
			if (!empty($this->Report))
			{
				return;
			}
		}
		else
		{
			$photo = $modx->config['site_url'].'assets/snippets/webloginpe/userimages/default_user.jpg';
		}
		
		// EVENT: OnBeforeWebSaveUser
		$NewUser = array();
		foreach ($_POST as $name => $value)
		{
			$NewUser[$name] = $value;
		}
		$this->OnBeforeWebSaveUser($NewUser, array());
		
		// If all that crap checks out, now we can create the account.
		$newUser = "INSERT INTO ".$web_users." (`username`, `password`, `cachepwd`) VALUES ('".$username."', '".$modx->db->escape($this->HashPassword($password))."', '".$cachepwd."')";
		$createNewUser = $modx->db->query($newUser);
		
		if (!$createNewUser)
		{
			return $this->FormatMessage($this->LanguageArray[9]);
		} 
		
		$key = $modx->db->getInsertId();
		$NewUser['internalKey'] = $key; // pixelchutes
		
		$newUserAttr = "INSERT INTO ".$web_user_attributes.
		" (internalKey, fullname, email, phone, mobilephone, dob, gender, country, state, zip, fax, photo, comment) VALUES".
		" ('".$key."', '".$fullname."', '".$email."', '".$phone."', '".$mobilephone."', ".(int)$dob.", ".(int)$gender.", '".$country."', '".$state."', '".$zip."', '".$fax."', '".$photo."', '".$comment."')";
		$insertUserAttr = $modx->db->query($newUserAttr);
				
		if (!$insertUserAttr)
		{
			return $this->FormatMessage($this->LanguageArray[10]);
		}
		
		if (!empty($this->CustomFields) && $this->CustomFields !== '')
		{
			$extendedFieldValues = array();
			foreach ($this->CustomFields as $field)
			{
				$extendedFieldValues[$field] = $modx->db->escape($this->PostString($field));
			}
			$extendedFieldValues = implode("', '", $extendedFieldValues);
			$extendedFields = implode('`, `', $this->CustomFields);
			$extendedUserAttr = "INSERT INTO ".$this->CustomTable." (`internalKey`, `".$extendedFields."`) VALUES ('".$key."', '".$extendedFieldValues."')";
			$insertExtendedAttr = $modx->db->query($extendedUserAttr);
		
			if (!$insertExtendedAttr)
			{
				return $this->FormatMessage($this->LanguageArray[10]);
			}
		}
		
		$groups = str_replace(', ', ',', $groups);
		$GLOBALS['groupsArray'] = explode(',', $groups);
		
		// EVENT: OnBeforeAddToGroup
		$this->OnBeforeAddToGroup($GLOBALS['groupsArray']);
		if (is_array($GLOBALS['groupsArray']) && count($GLOBALS['groupsArray']) > 0) // FIX PHP 8: count() su booleano = TypeError
		{
			$groupsList = "'".implode("','", $GLOBALS['groupsArray'])."'";
			$groupNames = $modx->db->query("SELECT `id` FROM ".$webgroup_names." WHERE `name` IN (".$groupsList.")");
			if (!$groupNames)
			{
				return $this->FormatMessage($this->LanguageArray[11]);
			}
			else
			{
				while ($row = $modx->db->getRow($groupNames))
				{
					$webGroupId = $row['id'];
					$modx->db->query("REPLACE INTO ".$web_groups." (`webgroup`, `webuser`) VALUES ('".$webGroupId."', '".$key."')");
				}
			}
		}
		
		// EVENT: OnWebSaveUser
		$this->OnWebSaveUser('new', $NewUser);
		
		// Replace some placeholders in the Config websignupemail message.
		$messageTpl = $modx->config['websignupemail_message'];
		$myEmail = $modx->config['emailsender'];
        $emailSubject = $modx->config['emailsubject'];
		$siteName = $modx->config['site_name'];
		$siteURL = $modx->config['site_url'];
		
		$message = str_replace('[+uid+]', $username, $messageTpl);
        $message = str_replace('[+pwd+]', $password, $message);
        $message = str_replace('[+ufn+]', $fullname, $message);
        $message = str_replace('[+sname+]', $siteName, $message);
        $message = str_replace('[+semail+]', $myEmail, $message);
        $message = str_replace('[+surl+]', $siteURL, $message);
		foreach ($_POST as $name => $value)
		{
			$toReplace = '[+post.'.$name.'+]';
			$message = str_replace($toReplace, $value, $message);
		}

		// Bring in php mailer!
		$Register = $this->mailer;
		$Register->CharSet = $modx->config['modx_charset'];
		$Register->From = $myEmail;
		$Register->FromName = $siteName;
		$Register->Subject = $emailSubject;
		$Register->Body = $message;
		$Register->AddAddress($email, $fullname);
		
		if (!$Register->Send())
		{
			return $this->FormatMessage($this->LanguageArray[12]);
		}
		
		// Add the list of administrators to be notified on new registration to a Blind Carbon Copy.
		if (isset($notify) && $notify !== '')
		{
			$emailList = str_replace(', ', ',', $notify);
			$emailArray = explode(',', $emailList);
			
			$notification = str_replace('[+uid+]', $username, $notifyTpl);
	        $notification = str_replace('[+ufn+]', $fullname, $notification);
	        $notification = str_replace('[+sname+]', $siteName, $notification);
	        $notification = str_replace('[+semail+]', $myEmail, $notification);
	        $notification = str_replace('[+surl+]', $siteURL, $notification);
			$notification = str_replace('[+uem+]', $email, $notification);
			foreach ($_POST as $name => $value)
			{
				$toReplace = '[+post.'.$name.'+]';
				$notification = str_replace($toReplace, $value, $notification);
			}
			
			$Notify = $this->mailer;
			$Notify->CharSet = $modx->config['modx_charset'];
			// FIX: stesso oggetto mailer della mail all'utente -> svuota i destinatari,
			// altrimenti la notifica admin arriva anche al nuovo utente
			$Notify->ClearAllRecipients();
			$Notify->ClearReplyTos();
			
			foreach ($emailArray as $address)
			{
				// FIX: mittente = indirizzo del sito (SPF/DMARC), l'utente va in Reply-To
				$Notify->From = $myEmail;
				$Notify->FromName = $siteName;
				$Notify->ClearReplyTos();
				$Notify->AddReplyTo($email, $fullname);
				$Notify->Subject = $notifySubject;
				$Notify->Body = $notification;
				$Notify->AddAddress($address);
				if (!$Notify->Send())
				{
					return $this->FormatMessage($Notify->ErrorInfo);
				}
				$Notify->ClearAddresses();
			}
		}
		$this->SessionHandler('destroy');
		$this->FormatMessage($this->LanguageArray[100].$modx->config['site_name']);
		return 'success';
	}
	
	
	/**
	 * PruneUsers will remove non-activated user accounts older than the number of days specified in $pruneDays.
	 *
	 * @param int $pruneDays The number of days to wait before removing non-activated users.
	 * @return void
	 * @author Scotty Delicious
	 */
	function PruneUsers($pruneDays)
	{
		global $modx;
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		$web_groups = $modx->getFullTableName('web_groups');
		
		$findDeadAccounts = $modx->db->query("SELECT * FROM ".$web_users." WHERE `cachepwd` != '' AND CHAR_LENGTH( `cachepwd` ) < 20");
		$deadAccounts = $this->FetchAll($findDeadAccounts);
		
		foreach ($deadAccounts as $user)
		{
			if ($user['cachepwd'] <= (time() - (60 * 60 * 24 * $pruneDays)) && $user['cachepwd'] != 0)
			{
				$deleteUser = $modx->db->query("DELETE FROM ".$web_users." WHERE `id`='".$user['id']."'");
				$deleteAttributes = $modx->db->query("DELETE FROM ".$web_user_attributes." WHERE `internalKey`='".$user['id']."'");
				$deleteFromGroups = $modx->db->query("DELETE FROM ".$web_groups." WHERE `webuser`='".$user['id']."'");
				
				// Email the Web Master regarding pruned accounts.
				$prunedMessage = str_replace('[+000+]', $user['username'], $this->LanguageArray[36]);
				$prunedMessage = str_replace('[+111+]', $this->FormatDate('%A %B %d, %Y', $user['cachepwd']), $prunedMessage);
				$emailsender = $modx->config['emailsender'];
				
				$Pruned = $this->mailer;
				$Pruned->CharSet = $modx->config['modx_charset'];
				$Pruned->From = $modx->config['emailsender'];
				$Pruned->FromName = 'WebLoginPE Pruning Agent';
				$Pruned->Subject = $this->LanguageArray[35];
				$Pruned->Body = $prunedMessage;
				$Pruned->AddAddress($emailsender);
				if (!$Pruned->Send())
				{
					return $this->FormatMessage($Pruned->ErrorInfo);
				}
				$Pruned->ClearAddresses();
			}
		}
	}
	
	
	/**
	 * Template takes a template parameter and checks to see if it is a chunk.
	 * If it is a chunk, returns the contents of the chunk, if it is not a chunk,
	 * tries to find a file of that name (or path) and gets its contents. If it
	 * is not a chunk or a file, returns the value passed as the parameter $chunk.
	 *
	 * @param string $chunk 
	 * @return string HTML block.
	 * @author Scotty Delicious
	 */
	function Template($chunk)
	{
		global $modx;
		
		$template = '';
		if ($modx->getChunk($chunk) != '')
		{
			$template = $modx->getChunk($chunk);
		}
		else if (is_file($chunk))
		{
			$template = file_get_contents($chunk);
		}
		else
		{
			$template = $chunk;
		}
		return $template;
	}
	
	
	/**
	 * SaveUserProfile
	 * Updates the web_user_attributes table for a given internalKey.
	 *
	 * @return void
	 * @author Scotty Delicious
	 */
	function SaveUserProfile($internalKey = '')
	{
		global $modx;
		if ($internalKey == '' || empty($internalKey))
		{
			$currentWebUser = $modx->getWebUserInfo($modx->getLoginUserID());
			$internalKey = $currentWebUser['internalKey'];
			$refreshSession = true;
		}
		else
		{
			$currentWebUser = $modx->getWebUserInfo($internalKey);
			$refreshSession = false;
		}
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		// EVENT: OnBeforeWebSaveUser
		$this->OnBeforeWebSaveUser(array(), array()); // pixelchutes
		if ( !empty($this->Report) ) return; // pixelchutes

		if (!empty($_POST['password']) && isset($_POST['password']) && isset($_POST['password_confirm'])) // pixelchutes
		{
			if ($_POST['password'] === $_POST['password_confirm']) // pixelchutes
			{
				// 1.4.0: la password viene usata cosi' com'e', sono ammessi anche i caratteri speciali
				if (strlen($this->PostString('password')) > 5)
				{
					$passwordElement = "UPDATE ".$web_users." SET `password`='".$modx->db->escape($this->HashPassword($this->PostString('password')))."' WHERE `id`='".(int)$internalKey."'";
					$saveMyPassword = $modx->db->query($passwordElement);
					$this->PurgeRememberTokens($internalKey, true); // 1.4.0: nuova password, i dispositivi "ricordati" vanno riautenticati
				}
				else
				{
					$this->FormatMessage($this->LanguageArray[3]);
					return;
				}
			}
			else
			{
				$this->FormatMessage($this->LanguageArray[2]);
				return;
			}
		}
		
		$fieldMessage = ''; // FIX PHP 8: variabile non inizializzata
		// Check for arrays and that "confirm" fields match.
		foreach ($_POST as $field => $value)
		{
			if (is_array($_POST[$field]))
			{
				$_POST[$field] = implode('||', $_POST[$field]);
			}
			
			$confirm = $field.'_confirm';
			if (isset($_POST[$confirm]))
			{
				if ($_POST[$field] !== $_POST[$confirm])
				{
					$error = $this->LanguageArray[2].' <br />';
					$fieldMessage .= str_replace('[+000+]', '"'.$field.'"', $error);
				}
			}
		}
		
		// If confirm fields were mismatched, throw this error:
		if (!empty($fieldMessage))
		{
			$err = $fieldMessage;
			unset($fieldMessage);
			return $this->FormatMessage($err);
		}
		
		$generalElementsArray = array('fullname','email','phone','mobilephone','dob','gender','country','state','street','city','zip','fax','photo','comment');
		$generalElementsUpdate = array();
		
		// CREDIT: Guillaume to delete data and for code optimisation
		foreach ($generalElementsArray as $field)
		{
			// FIX issue #5: un campo assente dal form non deve cancellare il valore salvato
			if (!$this->FieldSubmitted($field))
			{
				continue;
			}
			// FIX strict mode: dob e gender sono colonne INT
			if ($field == 'dob' || $field == 'gender')
			{
				$intValue = ($field == 'dob')
					? $this->DobToInt(isset($_POST['dob']) ? $_POST['dob'] : '')
					: (isset($_POST['gender']) ? (int)$_POST['gender'] : 0);
				$generalElementsUpdate[] = " `".$field."` = ".(int)$intValue;
				continue;
			}
			if ($field == 'photo')
			{
				if ($this->AllowPhoto && !empty($_FILES['photo']['name'])) // 1.4.0: upload solo con &allowPhoto
				{
					$_POST['photo'] = $this->CreateUserImage();
					if (!empty($this->Report))
					{
						return;
					}
				}
			}
			if ($field == 'dob' && trim($_POST['dob'])!='') // for not format an empty date else date is 0 (01-01-1970)
			{
				$_POST['dob'] = $this->MakeDateForDb($_POST['dob']);
			}
			if ($field!='photo' || (!empty($_FILES['photo']['name']))) // for update db with value and blank value (except if the field is 'photo')
{
	// CREDIT: Mike Reid (aka Pixelchutes) for the string escape code.
	$charset='"'.$modx->config['modx_charset'].'"';
	$generalElementsUpdate[] = " `".$field."` = '".$modx->db->escape(stripslashes(htmlentities(trim($this->PostString($field)), ENT_QUOTES, $modx->config['modx_charset'])))."'";
}
		}
		
		$extendedUserAttr = '';
		if (!empty($this->CustomFields) && $this->CustomFields !== '')
		{
			$checkForExtended = "SELECT * FROM ".$this->CustomTable." WHERE `internalKey` = '".$internalKey."'";
			$isExtended = $modx->db->query($checkForExtended);
			$extendedRows = $modx->db->getRow($isExtended);
			
			if (!empty($extendedRows))
			{
				$extendedFieldValues = array();
				foreach ($this->CustomFields as $field)
				{
					if (!$this->FieldSubmitted($field)) // FIX issue #5
					{
						continue;
					}
					// CREDIT: Mike Reid (aka Pixelchutes) for the string escape code.
					$extendedFieldValues[] = " `".$field."` = '".$modx->db->escape(stripslashes(trim($this->PostString($field))))."'";
				}
				$this->OnBeforeWebSaveUser($generalElementsUpdate, $extendedFieldValues);
				
				$extendedUserAttr = (count($extendedFieldValues) > 0)
					? "UPDATE ".$this->CustomTable." SET".implode(', ', $extendedFieldValues)." WHERE `internalkey` = '".$internalKey."'"
					: '';
			}
			else
			{
				$extendedFieldValues = array();
				foreach ($this->CustomFields as $field)
				{
					// CREDIT: Mike Reid (aka Pixelchutes) for the string escape code.
					$charset=$modx->config['modx_charset'];
					$extendedFieldValues[$field] = $modx->db->escape(stripslashes(htmlentities(trim($this->PostString($field)), ENT_QUOTES,$charset)));
				}
				$this->OnBeforeWebSaveUser($generalElementsUpdate, $extendedFieldValues);
				
				$extendedFieldValues = implode("', '", $extendedFieldValues);
				$extendedFields = implode('`, `', $this->CustomFields);
				$extendedUserAttr = "INSERT INTO ".$this->CustomTable." (`internalKey`, `".$extendedFields."`) VALUES ('".$internalKey."', '".$extendedFieldValues."')";
			}
		}
		// Prepare the query for General Elements
		$generalElementsSQL = "UPDATE ".$web_user_attributes." SET ".implode(', ', $generalElementsUpdate)." WHERE `internalkey` = '".$internalKey."'";
		
		// Execute the database queries.
		if( count($generalElementsUpdate) > 0 ) $saveMyProfile = $modx->db->query($generalElementsSQL);
		if (!empty($this->CustomFields) && $this->CustomFields !== '' && $extendedUserAttr !== '')
		{
			$insertExtendedAttr = $modx->db->query($extendedUserAttr);			
		}
		
		$this->User = $this->QueryDbForUser(is_array($currentWebUser) ? $currentWebUser['username'] : '');
		$this->OnWebSaveUser('update', $this->User);
		
		if ($refreshSession === true)
		{
			// FIX: Username non e' impostato durante il salvataggio del profilo,
			// senza questo webShortname in sessione diventava vuoto
			if (is_array($this->User) && isset($this->User['username']))
			{
				$this->Username = $this->User['username'];
			}
			$this->SessionHandler('start');
		}
		
		$this->FormatMessage($this->LanguageArray[101]);
	}
	
	
	
	function RemoveProfile($internalKey)
	{
		global $modx;
		$deletedUser = $modx->getWebUserInfo($internalKey);
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		$web_groups = $modx->getFullTableName('web_groups');
		$active_users = $modx->getFullTableName('active_users');
		
		$deleteUser = $modx->db->query("DELETE FROM ".$web_users." WHERE `id`='".$internalKey."'");
		$deleteAttributes = $modx->db->query("DELETE FROM ".$web_user_attributes." WHERE `internalKey`='".$internalKey."'");
		$deleteFromGroups = $modx->db->query("DELETE FROM ".$web_groups." WHERE `webuser`='".$internalKey."'");
		$deleteFromActiveUsers = $modx->db->query("DELETE FROM ".$active_users." WHERE `internalKey`='-".$internalKey."'");
		
		if (!$deleteUser || !$deleteAttributes || !$deleteFromGroups || !$deleteFromActiveUsers)
		{
			return $this->FormatMessage($this->LanguageArray[13]);
		}
		$this->OnWebDeleteUser($internalKey, is_array($deletedUser) ? $deletedUser['username'] : ''); // FIX: era $deleteUser (risultato della query)
		return;
	}
	
	
	/**
	 * RemoveUserProfile
	 * Deletes the table entries in web_users, web_user_attributes, and web_groups for a given internalKey.
	 *
	 * @return void
	 * @author Scotty Delicious
	 */
	function RemoveUserProfile()
	{
		global $modx;
		
		$currentWebUser = $modx->getWebUserInfo($modx->getLoginUserID());
		$internalKey = $currentWebUser['internalKey'];
		$this->RemoveProfile($internalKey);
		$this->SessionHandler('destroy');
		$this->FormatMessage($this->LanguageArray[102]);
		return;
	}
	
	
	function RemoveUserProfileManager($internalKey)
	{
		$this->RemoveProfile($internalKey);
		$this->FormatMessage($this->LanguageArray[102]);
		return;
	}
	
	
	/**
	 * View all users stored in the web_users table.
	 *
	 * @param string $userTemplate HTML template to display each web user.
	 * @return string HTML block containing all the users
	 * @author Scotty Delicious
	 */
	function ViewAllUsers($userTemplate, $outerTemplate, $listUsers)
	{
		global $modx;
		
		$web_users = $modx->getFullTableName('web_users');
		$fetchUsers = $modx->db->query("SELECT `username` FROM ".$web_users);
		$allUsers = $this->FetchAll($fetchUsers);
		$FinalDisplay = '';
		$displayUserTemplate = '';
		$CombinedList = '';
		
		if ($listUsers == '')
		{
			$listUsers = '[(site_name)] Members:default:default:username:ASC:';
		}

		if ($listUsers !== '')
		{
			$eachList = explode('||', $listUsers);
			foreach ($eachList as $eachListFormat)
			{
				$format = array_pad(explode(':', $eachListFormat), 6, '');
				$listName = $format[0];
				
				$listOuterTemplate = $format[1];
				if ($listOuterTemplate == 'default')
				{
					$listOuterTemplate = $outerTemplate;
				}
				else
				{
					$listOuterTemplate = $this->Template($listOuterTemplate);
				}
				//return $listOuterTemplate;
				
				$listTemplate = $format[2];
				if ($listTemplate == 'default')
				{
					$listTemplate = $userTemplate;
				}
				else
				{
					$listTemplate = $this->Template($listTemplate);
				}
				
				$listSortBy = $format[3];
				$listSortOrder = $format[4];
				if ($listSortOrder == 'DESC')
				{
					$listSortOrder = SORT_DESC;
				}
				else
				{
					$listSortOrder = SORT_ASC;
				}
				
				// Filters.
				if ($format[5] == '')
				{
					$format[5] = 'username()';
				}
				
				$CompleteUserList = array();
				foreach ($allUsers as $user)
				{
					$username = $user['username'];
					$CompleteUserList[$username] = $this->QueryDbForUser($username);
				}
				
				$allFilters = explode(',', str_replace(', ', ',', $format[5]));
				foreach ($allFilters as $theFilter)
				{
					$filters = array_pad(explode('(', $theFilter), 2, '');
					$filterBy = $filters[0];
					
					$sortNumerics = array('dob','lastlogin','thislogin','internalKey','logincount','blocked','blockeduntil','blockedafter','failedlogincount','gender');
					if (in_array($filterBy, $sortNumerics))
					{
						$typeFlag = SORT_NUMERIC;
					}
					else
					{
						$typeFlag = SORT_STRING;
					}
					
					$filterValue = str_replace(')', '', $filters[1]);
					if ($filterValue == '') unset($filterValue);
					
					foreach ($CompleteUserList as $theUser)
					{
						if ($filterBy == 'webgroup')
						{
							$web_groups = $modx->getFullTableName('web_groups');
							$webgroup_names = $modx->getFullTableName('webgroup_names');
							$findWebGroup = $modx->db->query("SELECT `id` FROM ".$webgroup_names." WHERE `name` = '".$filterValue."'");
							$limit = $modx->db->getRecordCount($findWebGroup);
							if ($limit == 0)
							{
								print 'There is no webgroup by the name "'.$filterValue.'"';
							}
							$webGroupIdSearch = $modx->db->getRow($findWebGroup);
							$webGroupId = $webGroupIdSearch['id'];
							
							$groupQuery = "SELECT * FROM ".$web_groups." WHERE `webgroup` = '".$webGroupId."' AND `webuser` = '".$theUser['internalKey']."'";
							$isMember = $modx->db->query($groupQuery);
							$limit = $modx->db->getRecordCount($isMember);
							if ($limit == 0)
							{
								$username = $theUser['username'];
								unset($CompleteUserList[$username]);
							}
						}
						else if ($filterBy == 'online')
						{
							$active_users = $modx->getFullTableName('active_users');
							$activityCheck = "SELECT * FROM ".$active_users." WHERE `internalKey` = '-".$theUser['internalKey']."'";
							$lastActive = $modx->db->query($activityCheck);
							$limit = $modx->db->getRecordCount($lastActive);
							if ($limit !== 0)
							{
								$userStatus = $modx->db->getRow($lastActive);
								if ($userStatus['lasthit'] >= time() - (60 * 15))
								{
									// Good, User is online and active
								}
								else
								{
									$username = $theUser['username'];
									unset($CompleteUserList[$username]);
								}
							}
							else
							{
								$username = $theUser['username'];
								unset($CompleteUserList[$username]);
							}
						}
						else
						{
							foreach ($theUser as $attribute => $value)
							{
								if (empty($theUser[$filterBy]) || $theUser[$filterBy] == '' && $filterBy !== 'webgroup')
								{
									$username = $theUser['username'];
									unset($CompleteUserList[$username]);
								}

								if (isset($filterValue) && $filterBy !== 'webgroup')
								{
									if ($theUser[$filterBy] !== '' && !empty($theUser[$filterBy]))
									{
										$isValue = strpos(strtolower($filterValue), strtolower($theUser[$filterBy]));
										$isValueAlt = strpos(strtolower($theUser[$filterBy]), strtolower($filterValue));
										if ($isValue === false && $isValueAlt === false)
										{
											$username = $theUser['username'];
											unset($CompleteUserList[$username]);
										}
									}
								}
							}
						}
					}
				}
				// SORT ARRAY
				$sortArray = array();
			    foreach($CompleteUserList as $username => $attributes)
				{
			        foreach($attributes as $field => $value)
					{
			            $sortArray[$field][$username] = $value;
			        }
			    }
				//List here
				if (isset($sortArray[$listSortBy]) && is_array($sortArray[$listSortBy]))
				{
					$arrayMap = array_map(function ($v) { return strtolower((string)$v); }, $sortArray[$listSortBy]);
					array_multisort($arrayMap, $listSortOrder, $typeFlag, $CompleteUserList);
					
					foreach ($CompleteUserList as $theUser)
					{
						$user = $this->QueryDbForUser($theUser['username']);

						$active_users = $modx->getFullTableName('active_users');
						$activityCheck = "SELECT * FROM ".$active_users." WHERE `internalKey` = '-".$theUser['internalKey']."'";
						$lastActive = $modx->db->query($activityCheck);
						$limit = $modx->db->getRecordCount($lastActive);
						if ($limit !== 0)
						{
							$userStatus = $modx->db->getRow($lastActive);
							if ($userStatus['lasthit'] >= time() - (60 * 30))
							{
								$user['status'] = $this->LanguageArray[41];
							}
							else
							{
								$user['status'] = $this->LanguageArray[42];
							}
						}
						else
						{
							$user['status'] = $this->LanguageArray[42];
						}

						$eachProfile = $listTemplate;
						foreach ($user as $field => $value)
						{
							// $value = html_entity_decode($value);
							$needToSplit = strpos((string)$value, '||');
							if ($needToSplit > 0)
							{
								$user[$field] = str_replace('||', ', ', $value);
							}

							$placeholder = '[+view.'.$field.'+]';

							if ($field == 'dob')
							{
								if ($value == 0)
								{
									$value = $this->LanguageArray[33];
									$eachProfile = str_replace('[+view.age+]', $value, $eachProfile);
								}
								else
								{
									$ageDecimal = ((time() - $value) / (60 * 60 * 24 * 365));
									$age = substr($ageDecimal, 0, strpos($ageDecimal, "."));
									$value = $this->FormatDate($this->DobStrftimeFormat(), $value);
									$eachProfile = str_replace('[+view.age+]', $age, $eachProfile);
								}
							}
							else if ($field == 'lastlogin' || $field == 'thislogin')
							{
								if ($value == 0)
								{
									$value = $this->LanguageArray[33];
								}
								else
								{
									$value = $this->FormatDate($this->DateFormat, $value);
								}
							}


							$eachProfile = str_replace($placeholder, $this->EscapeOutput($value), $eachProfile); // 1.4.0: escape XSS
						}
						$displayUserTemplate .= $eachProfile;
					}
					$CombinedList = str_replace('[+view.title+]', $listName, $listOuterTemplate);
					$CombinedList = str_replace('[+view.list+]', $displayUserTemplate, $CombinedList);
					$displayUserTemplate = NULL;
				}
				$FinalDisplay .= $CombinedList;
			}
		}
		return $FinalDisplay;
	}
	
	
	/**
	 * ViewUserProfile displays sets the placeholders for the attributes of another site user
	 *
	 * @param string $username The username of the other user's profile to view
	 * @return void
	 * @author Scotty Delicious
	 */
	function ViewUserProfile($username,$inputHandler=array())
	{
		global $modx;
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		$findID = $modx->db->query("SELECT * FROM ".$web_users." WHERE `username` = '".$username."'");
		$userID = $modx->db->getRow($findID);
		$userID = is_array($userID) ? $userID['id'] : 0;
		$viewUser = $modx->getWebUserInfo($userID);
		$modx->setPlaceholder('view.username', is_array($viewUser) ? $this->EscapeOutput($viewUser['username']) : '');
		$allFields = $modx->db->query("SELECT * FROM ".$web_user_attributes.", ".$this->CustomTable." WHERE ".$web_user_attributes.".`internalKey` = '".$userID."' AND ".$this->CustomTable.".`internalKey` = '".$userID."'");
		$limit = $modx->db->getRecordCount($allFields);
		
		if ($limit == 0)
		{
			$allFields = $modx->db->query("SELECT * FROM ".$web_user_attributes." WHERE ".$web_user_attributes.".`internalKey` = '".$userID."'");
		}
		
		$viewUser = $modx->db->getRow($allFields);
		if (!is_array($viewUser))
		{
			$viewUser = array('internalKey' => 0, 'gender' => 0, 'country' => '');
		}
		
		$active_users = $modx->getFullTableName('active_users');
		$activityCheck = "SELECT * FROM ".$active_users." WHERE `internalKey` = '-".$viewUser['internalKey']."'";
		$lastActive = $modx->db->query($activityCheck);
		$limit = $modx->db->getRecordCount($lastActive);
		if ($limit !== 0)
		{
			$userStatus = $modx->db->getRow($lastActive);
			if ($userStatus['lasthit'] >= time() - (60 * 30))
			{
				$viewUser['status'] = $this->LanguageArray[41];
			}
			else
			{
				$viewUser['status'] = $this->LanguageArray[42];
			}
		}
		else
		{
			$viewUser['status'] = $this->LanguageArray[42];
		}
		
		foreach ($viewUser as $column => $setting)
		{
			// $setting = html_entity_decode($setting);
			$needToSplit = strpos((string)$setting, '||');
			if ($needToSplit > 0)
			{
				$viewUser[$column] = str_replace('||', ', ', $setting);
			}
			
			$modx->setPlaceholder('view.'.$column, $this->EscapeOutput(stripslashes($viewUser[$column]))); // 1.4.0: escape XSS
			
			if ($column == 'dob')
			{
				if ($setting == 0)
				{
					if ($this->Type !== 'manager')
					{
						$modx->setPlaceholder('view.dob', $this->LanguageArray[33]);
					}
					else
					{
						$modx->setPlaceholder('view.dob', '');
					}
					$modx->setPlaceholder('view.age', $this->LanguageArray[33]);
				}
				else
				{
					$ageDecimal = ((time() - $setting) / (60 * 60 * 24 * 365));
					$age = substr($ageDecimal, 0, strpos($ageDecimal, "."));
					$modx->setPlaceholder('view.dob', $this->FormatDate($this->DobStrftimeFormat(), $viewUser['dob']));
					$modx->setPlaceholder('view.age', $age);
				}
			}
			if ($column == 'lastlogin')
			{
				$modx->setPlaceholder('view.lastlogin', $this->FormatDate($this->DateFormat, $viewUser['lastlogin']));
			}
			if ($column == 'thislogin')
			{
				$modx->setPlaceholder('view.thislogin', $this->FormatDate($this->DateFormat, $viewUser['thislogin']));
			}
			
			if ($this->Type !== 'manager')
			{
				$private = strpos($column, 'private');
				if ($private > 0)
				{
					if ($setting == 'on')
					{
						$fieldToReplace = str_replace('private', '', $column);
						$viewUser[$fieldToReplace] = $this->LanguageArray[38];
						$modx->setPlaceholder('view.'.$fieldToReplace, $this->LanguageArray[38]);
					}
				}// end private
			}
		}
		$modx->setPlaceholder('view.gender', $this->StringForGenderInt($viewUser['gender']));
		$modx->setPlaceholder('view.country', $this->StringForCountryInt($viewUser['country']));
		
		// Handle Special input placeholders.
		include 'assets/snippets/webloginpe/Default Forms/countryCodes.php';
		$inputHandler[9998] = str_replace('[+COUNTRYLABEL+]', $this->LanguageArray[39], $countryCodes);
		$inputHandler[9999] = str_replace('[+GENDERLABEL+]', $this->LanguageArray[40], $genderCodes);

		foreach ($inputHandler as $value)
		{
			$dataType = array_pad(explode(':', $value), 5, '');
			$label = $dataType[0];
			$DOMid = $dataType[1];
			$name = $dataType[2];
			$type = $dataType[3];
			$values = $dataType[4];
			if ($type == 'checkbox' && $name !== '')
			{
				$this->CheckboxFields[$name] = true; // FIX issue #5
			}

			if ($type == 'select multiple' || $type == 'select')
			{
				$ph = '';
				$ph .= '<label for="'.$DOMid.'" id="'.$DOMid.'Label">'.$label."\n";
				if ($type == 'select multiple')
				{
					$ph .= '<'.$type.' id="'.$DOMid.'" name="'.$name.'[]">'."\n";
				}
				else
				{
					$ph .= '<'.$type.' id="'.$DOMid.'" name="'.$name.'">'."\n";
				}
				$options = explode(',', $values);
				foreach ($options as $eachOption)
				{
					$option = array_pad(explode('(', $eachOption), 2, '');
					$option = str_replace(')', '', $option);
					
					if (isset($viewUser[$name]))
					{
						if (is_array($viewUser[$name]))
						{
							if (in_array($option[1], $viewUser[$name]))
							{
								$ph .= "\t".'<option selected="selected" value ="'.$option[1].'">'.$option[0].'</option>'."\n";

							}
							else
							{
								$ph .= "\t".'<option value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}
						}
						
						
						else 
						{
							if ($option[1] == $this->PostValue($name, null))
							{
								$ph .= "\t".'<option selected="selected" value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}
							
							else if ($option[1] == $viewUser[$name])
							{
								$ph .= "\t".'<option selected="selected" value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}

							else
							{
								$ph .= "\t".'<option value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}
						}
					}
					else
					{
						$ph .= "\t".'<option value ="'.$option[1].'">'.$option[0].'</option>'."\n";
					}
				}
				$ph .= '</select>'."\n";
				$ph .= '</label>'."\n";
				// Set the Placeholder
				$modx->setPlaceholder('form.'.$name, $ph);
			}

			if ($type == 'radio')
			{
				$ph = '';
				$ph .= '<label for="'.$DOMid.'" id="'.$DOMid.'Label">'.$label."\n";
				$ph .= '<div id="'.$DOMid.'Div">'."\n";
				$options = explode(',', $values);
				foreach ($options as $eachOption)
				{
					$option = array_pad(explode('(', $eachOption), 2, '');
					$option = str_replace(')', '', $option);
					if (isset($viewUser[$name]))
					{
						if ($option[1] == $viewUser[$name])
						{
							$ph .= '</span><input type="radio" id="'.$DOMid.$option[0].'" name="'.$name.'" value="'.$option[1].'" checked="checked" /><span class="'.$DOMid.'Span">'.$option[0].'</span>'."\n";
						}
						else
						{
							$ph .= '<input type="radio" id="'.$DOMid.$option[0].'" name="'.$name.'" value="'.$option[1].'" /><span class="'.$DOMid.'Span">'.$option[0].'</span>'."\n";
						}
					}
					else
					{
						$ph .= '<input type="radio" id="'.$DOMid.$option[0].'" name="'.$name.'" value="'.$option[1].'" /><span class="'.$DOMid.'Span">'.$option[0].'</span>'."\n";
					}
				}
				$ph .= '</div>'."\n";
				$ph .= '</label>'."\n";
				// Set the Placeholder
				$modx->setPlaceholder('form.'.$name, $ph);
			}

			if ($type == 'checkbox')
			{
				$ph = '';
				$ph .= '<label for="'.$DOMid.'" id="'.$DOMid.'Label">'.$label."\n";
				$options = explode(',', $values);
				foreach ($options as $eachOption)
				{
					$option = array_pad(explode('(', $eachOption), 2, '');
					$option = str_replace(')', '', $option);
					// FIX: valore della checkbox configurabile da &inputHandler, es. :id:newsletter:checkbox:(si)
					// senza valore tra parentesi il browser invia 'on' come prima
					$cbValue = ($option[1] !== '') ? $option[1] : 'on';
					$cbValueAttr = ($option[1] !== '') ? ' value="'.htmlspecialchars($option[1], ENT_QUOTES).'"' : '';
					$cbChecked = (isset($viewUser[$name]) && ($viewUser[$name] == $cbValue || $viewUser[$name] == 'on')) ? ' checked="checked"' : '';
					$ph .= $option[0].' <input type="checkbox" id="'.$DOMid.'" name="'.$name.'"'.$cbValueAttr.$cbChecked.' />'."\n";
				}
				$ph .= '</label>'."\n";
				// Set the Placeholder
				$modx->setPlaceholder('form.'.$name, $ph);
			}
		}
		//$modx->setPlaceholder('form.gender', 'YOU FUckTard!');
		
		$viewUser = '';
	}
	
	
	/**
	 * SendMessageToUser allows site users to send email messages to each other.
	 *
	 * @return void.
	 * @author Scotty Delicious
	 */
	function SendMessageToUser()
	{
		global $modx;
		
		$me = $modx->getWebUserInfo($modx->db->escape($this->PostString('me')));
		$you = $modx->getWebUserInfo($modx->db->escape($this->PostString('you')));
		$subject = $modx->db->escape($this->PostString('subject'));
		$message = stripslashes(strip_tags($this->PostString('message')))."\n\n".$modx->config['site_name'];
		
		if (empty($subject) || $subject == '' || empty($message) || $message == '')
		{
			$this->FormatMessage($this->LanguageArray[0]);
			$this->ViewUserProfile($you['username']);
			return;
		}
		
		$EmailMessage = $this->mailer;
		$EmailMessage->CharSet = $modx->config['modx_charset'];
		$EmailMessage->From = $me['email'];
		$EmailMessage->FromName = $me['fullname']." (".$me['username'].")";
		$EmailMessage->Subject = $subject;
		$EmailMessage->Body = $message;
		$EmailMessage->AddAddress($you['email'], $you['fullname']);

		if (!$EmailMessage->Send())
		{
			$this->FormatMessage($EmailMessage->ErrorInfo);
			$this->ViewUserProfile($you['username']);
			return;
		}
		$this->FormatMessage($this->LanguageArray[37].' "'.$you['username'].'"');
		$this->ViewUserProfile($you['username']);
		return;
	}
	
	/**
	 * ResetPassword
	 * Sets a random password | random key in the web_users.cachepwd field,
	 * then sends an email to the user with instructions and a URL to activate.
	 *
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function ResetPassword()
	{
		global $modx;
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		$email = $modx->db->escape(trim($this->PostString('email'))); // pixelchutes
		if ( empty($email) ) return $this->FormatMessage($this->LanguageArray[0]); // pixelchutes        
        $webpwdreminder_message = $modx->config['webpwdreminder_message'];
        $emailsubject = $modx->config['emailsubject'];
		$site_name = $modx->config['site_name'];
		$emailsender = $modx->config['emailsender'];
		
		$findUser = "SELECT * FROM ".$web_user_attributes.", ".$web_users." WHERE `email`='".$email."' AND `internalKey`=".$web_users.".`id`";
		$userInfo = $modx->db->query($findUser);
		$limit = $modx->recordCount($userInfo);
		
		if ($limit == 1)
		{
			// Reset the password and fire off an email to the user
			$newPassword = $this->GeneratePassword(10);
			$newPasswordKey = $this->GeneratePassword(10);
			$this->User = $modx->db->getRow($userInfo);
			$insertNewPassword = "UPDATE ".$web_users." SET cachepwd='".$modx->db->escape($this->HashTempPassword($newPassword)."|".$newPasswordKey)."' WHERE id='".(int)$this->User['internalKey']."'"; // 1.4.0: password temporanea non in chiaro
			$setCachePassword = $modx->db->query($insertNewPassword);
			
			// build activation url
            if($_SERVER['SERVER_PORT']!='80')
			{
				$url = $modx->config['server_protocol'].'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$modx->makeURL($modx->documentIdentifier,'',"&service=activate&userid=".$this->User['id']."&activationkey=".$newPasswordKey);
			}
			else
			{
				//$url = $modx->config['server_protocol'].'://'.$_SERVER['SERVER_NAME'].$modx->makeURL($modx->documentIdentifier,'',"&service=activate&userid=".$this->User['id']."&activationkey=".$newPasswordKey);
				$url = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '')."&service=activate&userid=".$this->User['id']."&activationkey=".$newPasswordKey;
            }
			
			$message = str_replace("[+uid+]", $this->User['username'], $webpwdreminder_message);
            $message = str_replace("[+pwd+]", $newPassword, $message);
            $message = str_replace("[+ufn+]", $this->User['fullname'], $message);
            $message = str_replace("[+sname+]", $site_name, $message);
            $message = str_replace("[+semail+]", $emailsender, $message);
            $message = str_replace("[+surl+]", $url, $message);

			$Reset = $this->mailer;
			$Reset->CharSet = $modx->config['modx_charset'];
			$Reset->From = $emailsender;
			$Reset->FromName = $site_name;
			$Reset->Subject = $emailsubject;
			$Reset->Body = $message;
			$Reset->AddAddress($email, $this->User['fullname']);

			if (!$Reset->Send())
			{
				return $this->FormatMessage($this->LanguageArray[12]);
			}
		}
		else
		{
			return $this->FormatMessage($this->LanguageArray[14]);
		}
		$this->FormatMessage($this->LanguageArray[103]);
		return;
	}
	
	
	/**
	 * ActivateUser
	 * Activates the user after they have requested to have their password reset.
	 *
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function ActivateUser()
	{
		global $modx;
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		$userid = $modx->db->escape(isset($_REQUEST['userid']) ? $_REQUEST['userid'] : '');
		$activationKey = $modx->db->escape(isset($_REQUEST['activationkey']) ? $_REQUEST['activationkey'] : '');
		$passwordKey = $modx->db->escape($this->PostString('activationpassword'));
		$newPassword = $this->PostString('newpassword'); // 1.4.0: usata solo per l'hash, mai nelle query
		$newPasswordConfirm = $this->PostString('newpassword_confirm'); // pixelchutes 1:55 AM 9/19/2007
		
		$findUser = "SELECT * FROM ".$web_users." WHERE id='".$userid."'";
		$userInfo = $modx->db->query($findUser);
        $limit = $modx->recordCount($userInfo);
		
		if ($limit !==1)
		{
			return $this->FormatMessage($this->LanguageArray[15]);
		}
		
		$this->User = $modx->db->getRow($userInfo);
		list($cachePassword, $cacheKey) = array_pad(explode("|", (string)$this->User['cachepwd'], 2), 2, '');
		
		// FIX sicurezza (1.3.5): deve esistere una richiesta di reset reale.
		// Con cachepwd vuoto (utente gia' attivo) o con il solo timestamp di registrazione,
		// chiavi vuote venivano accettate e si poteva cambiare la password di qualsiasi utente.
		if ($cachePassword === '' || $cacheKey === '' || strlen($cacheKey) < 10
			|| !hash_equals($cacheKey, (string)$activationKey) || !$this->VerifyTempPassword((string)$passwordKey, $cachePassword))
		{
			return $this->FormatMessage($this->LanguageArray[16]);
		}
		
		$saveMyPassword = false; // FIX PHP 8: variabili non inizializzate
		$unblockUser = false;
		if (!empty($newPassword) && isset($newPassword) && isset($newPasswordConfirm))
		{
			if ($newPassword === $newPasswordConfirm)
			{
				// 1.4.0: la password viene usata cosi' com'e', sono ammessi anche i caratteri speciali
				if (strlen($newPassword) > 5)
				{
					$passwordElement = "UPDATE ".$web_users." SET `password`='".$modx->db->escape($this->HashPassword($newPassword))."', cachepwd='' WHERE `id`='".(int)$this->User['id']."'";
					$saveMyPassword = $modx->db->query($passwordElement);
					$this->PurgeRememberTokens($this->User['id'], true); // 1.4.0
					
					$blocks = "UPDATE ".$web_user_attributes." SET `blocked`='0', `blockeduntil`='0' WHERE `internalKey`='".$this->User['id']."'";
					$unblockUser = $modx->db->query($blocks);
					
					// EVENT: OnWebChangePassword
					$this->OnWebChangePassword($this->User['id'], $this->User['username'], $newPassword);
				}
				else
				{
					return $this->FormatMessage($this->LanguageArray[3]);
				}
			}
			else
			{
				return $this->FormatMessage($this->LanguageArray[2]);
			}
		}
		if(!$saveMyPassword || !$unblockUser)
		{ 
			return $this->FormatMessage($this->LanguageArray[17]);
		}
		$this->FormatMessage($this->LanguageArray[104]);
		return;
	}
	
	
	/**
	 * PlaceHolders
	 * Sets place holders using the MODx method setPlaceholder() for fields in web_user_attributes.
	 *
	 * @param string $dateFormat The strftime() format set in the calling script.
	 * @param array $inputHandler An array of inputs to... uhh... handle?
	 * @param string $UserImageSettings The specifications for the user image.
	 * @param string $MessageTemplate The template for $this->Report.
	 * @return void
	 * @author Scotty Delicious
	 */
	function PlaceHolders($inputHandler, $MessageTemplate = '[+wlpe.message.text+]')
	{
		global $modx;

		$this->MessageTemplate = $MessageTemplate;
		$CurrentUser = $modx->getWebUserInfo($modx->getLoginUserID());
		$modx->setPlaceholder('user.username', (is_array($CurrentUser) && isset($CurrentUser['username'])) ? $this->EscapeOutput($CurrentUser['username']) : '');
	
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		$extraFields = $modx->db->query("SELECT * FROM ".$this->CustomTable.", ".$web_user_attributes." WHERE ".$web_user_attributes.".`internalKey` = '".$modx->getLoginUserID()."' AND ".$this->CustomTable.".`internalKey` = '".$modx->getLoginUserID()."'");
		$limit = $modx->db->getRecordCount($extraFields);
		
		if ($limit == 0)
		{
			$extraFields = $modx->db->query("SELECT * FROM ".$web_user_attributes." WHERE ".$web_user_attributes.".`internalKey` = '".$modx->getLoginUserID()."'");
		}
		
		if ($modx->getLoginUserID() && $extraFields)
		{
			$CurrentUser = $modx->db->getRow($extraFields);
			if (!empty($CurrentUser))
			{
				foreach ($CurrentUser as $key => $value)
				{
					// $value = html_entity_decode($value);
					if ($key == 'id')
					{
						// Do Nothing, we don't need that shit in the placeholders.
					}
					else if ($key == 'dob')
					{
						// CREDIT : Guillaume for not format an empty date
						$value==0?'':$modx->setPlaceholder('user.'.$key, $this->FormatDate($this->DobStrftimeFormat(), $value));
						$modx->setPlaceholder('user.age', $this->AgeFromTimestamp($value)); // FIX: prima restituiva un anno, non l'eta'
						
					}
					else if ($key == 'thislogin' || $key == 'lastlogin')
					{
						if ($value == 0)
						{
							$modx->setPlaceholder('user.'.$key, $this->LanguageArray[33]);
						}
						else
						{
							$modx->setPlaceholder('user.'.$key, $this->FormatDate($this->DateFormat, $value));
						}
					}
					else if ($key == 'country')
					{
						$modx->setPlaceholder('user.country.integer', stripslashes($value));
						$modx->setPlaceholder('user.country', $this->StringForCountryInt($value));
					}
					else if ($key == 'gender')
					{
						$modx->setPlaceholder('user.gender.integer', stripslashes($value));
						$modx->setPlaceholder('user.gender', $this->StringForGenderInt($value));
					}
					else
					{
						$modx->setPlaceholder('user.'.$key, $this->EscapeOutput(stripslashes($value))); // 1.4.0: escape XSS						
					}
					
					$needToSplit = strpos((string)$value, '||');
					if ($needToSplit > 0)
					{
						$CurrentUser[$key] = explode('||', $value);
					}
				}
			}
		}
		// 1.4.0: token CSRF
		$csrfToken = $this->CsrfToken();
		$modx->setPlaceholder('wlpe.csrf', $csrfToken);
		$modx->setPlaceholder('form.csrf', '<input type="hidden" name="wlpe_csrf" value="'.$csrfToken.'" />');
		$modx->setPlaceholder('user.defaultphoto', 'assets/snippets/webloginpe/userimages/default_user.jpg');
		$modx->setPlaceholder('request.userid', isset($_REQUEST['userid']) ? htmlspecialchars((string)$_REQUEST['userid'], ENT_QUOTES) : '');
		$modx->setPlaceholder('request.activationkey', isset($_REQUEST['activationkey']) ? htmlspecialchars((string)$_REQUEST['activationkey'], ENT_QUOTES) : '');
		$modx->setPlaceholder('form.captcha', 'manager/includes/veriword.php');
		
		// Handle Special input placeholders.
		include MODX_BASE_PATH.'assets/snippets/webloginpe/Default Forms/countryCodes.php'; // FIX: con include_once alla seconda chiamata (dopo il salvataggio del profilo) le variabili non esistono
		$inputHandler[9998] = str_replace('[+COUNTRYLABEL+]', $this->LanguageArray[39], $countryCodes);
		$inputHandler[9999] = str_replace('[+GENDERLABEL+]', $this->LanguageArray[40], $genderCodes);

		foreach ($inputHandler as $value)
		{
			$dataType = array_pad(explode(':', $value), 5, '');
			$label = $dataType[0];
			$DOMid = $dataType[1];
			$name = $dataType[2];
			$type = $dataType[3];
			$values = $dataType[4];
			if ($type == 'checkbox' && $name !== '')
			{
				$this->CheckboxFields[$name] = true; // FIX issue #5
			}

			if ($type == 'select multiple' || $type == 'select')
			{
				$ph = '';
				$ph .= '<label for="'.$DOMid.'" id="'.$DOMid.'Label">'.$label."\n";
				if ($type == 'select multiple')
				{
					$ph .= '<'.$type.' id="'.$DOMid.'" name="'.$name.'[]">'."\n";
				}
				else
				{
					$ph .= '<'.$type.' id="'.$DOMid.'" name="'.$name.'">'."\n";
				}
				$options = explode(',', $values);
				foreach ($options as $eachOption)
				{
					$option = array_pad(explode('(', $eachOption), 2, '');
					$option = str_replace(')', '', $option);
					if (isset($CurrentUser[$name]))
					{
						if (is_array($CurrentUser[$name]))
						{
							if (in_array($option[1], $CurrentUser[$name]))
							{
								$ph .= "\t".'<option selected="selected" value ="'.$option[1].'">'.$option[0].'</option>'."\n";

							}
							else
							{
								$ph .= "\t".'<option value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}
						}
						else 
						{
							if ($option[1] == $this->PostValue($name, null))
							{
								$ph .= "\t".'<option selected="selected" value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}

							else if ($option[1] == $CurrentUser[$name])
							{
								$ph .= "\t".'<option selected="selected" value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}

							else
							{
								$ph .= "\t".'<option value ="'.$option[1].'">'.$option[0].'</option>'."\n";
							}
						}
					}
					else
					{
						$ph .= "\t".'<option value ="'.$option[1].'">'.$option[0].'</option>'."\n";
					}
				}
				$ph .= '</select>'."\n";
				$ph .= '</label>'."\n";
				// Set the Placeholder
				$modx->setPlaceholder('form.'.$name, $ph);
			}

			if ($type == 'radio')
			{
				$ph = '';
				$ph .= '<label for="'.$DOMid.'" id="'.$DOMid.'Label">'.$label."\n";
				$ph .= '<div id="'.$DOMid.'Div">'."\n";
				$options = explode(',', $values);
				foreach ($options as $eachOption)
				{
					$option = array_pad(explode('(', $eachOption), 2, '');
					$option = str_replace(')', '', $option);
					if (isset($CurrentUser[$name]))
					{
						if ($option[1] == $CurrentUser[$name])
						{
							$ph .= '</span><input type="radio" id="'.$DOMid.$option[0].'" name="'.$name.'" value="'.$option[1].'" checked="checked" /><span class="'.$DOMid.'Span">'.$option[0].'</span>'."\n";
						}
						else
						{
							$ph .= '<input type="radio" id="'.$DOMid.$option[0].'" name="'.$name.'" value="'.$option[1].'" /><span class="'.$DOMid.'Span">'.$option[0].'</span>'."\n";
						}
					}
					else
					{
						$ph .= '<input type="radio" id="'.$DOMid.$option[0].'" name="'.$name.'" value="'.$option[1].'" /><span class="'.$DOMid.'Span">'.$option[0].'</span>'."\n";
					}
				}
				$ph .= '</div>'."\n";
				$ph .= '</label>'."\n";
				// Set the Placeholder
				$modx->setPlaceholder('form.'.$name, $ph);
			}

			if ($type == 'checkbox')
			{
				$ph = '';
				$ph .= '<label for="'.$DOMid.'" id="'.$DOMid.'Label">'.$label."\n";
				$options = explode(',', $values);
				foreach ($options as $eachOption)
				{
					$option = array_pad(explode('(', $eachOption), 2, '');
					$option = str_replace(')', '', $option);
					// FIX: valore della checkbox configurabile da &inputHandler, es. :id:newsletter:checkbox:(si)
					// senza valore tra parentesi il browser invia 'on' come prima
					$cbValue = ($option[1] !== '') ? $option[1] : 'on';
					$cbValueAttr = ($option[1] !== '') ? ' value="'.htmlspecialchars($option[1], ENT_QUOTES).'"' : '';
					$cbChecked = (isset($CurrentUser[$name]) && ($CurrentUser[$name] == $cbValue || $CurrentUser[$name] == 'on')) ? ' checked="checked"' : '';
					$ph .= $option[0].' <input type="checkbox" id="'.$DOMid.'" name="'.$name.'"'.$cbValueAttr.$cbChecked.' />'."\n";
				}
				$ph .= '</label>'."\n";
				// Set the Placeholder
				$modx->setPlaceholder('form.'.$name, $ph);
			}
		}
		
		if (!empty($_POST))
		{
			foreach ($_POST as $key => $value)
			{
				$modx->setPlaceholder('post.'.$key, $this->EscapeOutput($value)); // 1.4.0: escape XSS
			}
		}
	}                               
	
	
	/**
	 * Authenticate
	 * Authenticates the user or sets failure counts on error.
	 *
	 * @return void
	 * @author Scotty Delicious
	 */
	function Authenticate()
	{
		global $modx;
		if (!empty($this->Report))
		{
			return; //There was an error in the last step
		}
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		$authenticate = $this->OnWebAuthentication();
		// check if there is a plugin to authenticate user and that said plugin authenticated the user
		// else use a simple authentication scheme comparing MD5 of password to database password.
	    if (!$authenticate || (is_array($authenticate) && !in_array(TRUE, $authenticate)))
		{
			// 1.4.0: password_hash() con compatibilita' MD5; il login da token "ricordami" e' gia' verificato
			if (!$this->TokenLogin)
			{
				if (!$this->VerifyPassword($this->Password, $this->User['password']))
				{
					$this->LoginErrorCount = 1;
				}
				elseif ($this->PasswordNeedsUpgrade($this->User['password']))
				{
					// conversione automatica della vecchia password MD5
					$newHash = $this->HashPassword($this->Password);
					$modx->db->query("UPDATE ".$web_users." SET `password`='".$modx->db->escape($newHash)."' WHERE `id`='".(int)$this->User['internalKey']."'");
					$this->User['password'] = $newHash;
				}
			}
	    }
		
		if ($this->LoginErrorCount == 1)
		{
	        $this->User['failedlogincount'] += $this->LoginErrorCount;
			
	        if ($this->User['failedlogincount'] >= $modx->config['failed_login_attempts'])
			{ //increment the failed login counter, and block!
	            $sql = "UPDATE ".$web_user_attributes." SET `failedlogincount`='0', `blockeduntil`='".(time() + ($modx->config['blocked_minutes'] * 60))."' WHERE `internalKey`='".$this->User['internalKey']."'";
	            $failLoginAndBlockUser = $modx->db->query($sql);
				$anError = str_replace('[+000+]', $modx->config['blocked_minutes'], $this->LanguageArray[19]);
				$this->FormatMessage($anError);
	        	return;
			}
			else
			{ //increment the failed login counter
	            $sql = "UPDATE ".$web_user_attributes." SET failedlogincount='".$this->User['failedlogincount']."' WHERE internalKey='".$this->User['internalKey']."'";
	            $updateFailedLoginCount = $modx->db->query($sql);
				
				// Get a fresh copy of the user attributes.
				$this->User = $this->QueryDbForUser($this->User['username']);
				
				$failedLoginCount = $this->User['failedlogincount'];
				
				$anError = $this->LanguageArray[20];
				$anError = str_replace('[+000+]', $failedLoginCount, $anError);
				$anError = str_replace('[+111+]', $modx->config['blocked_minutes'], $anError);
				$anError = str_replace('[+222+]', $modx->config['failed_login_attempts'], $anError);
				
				$this->LoginErrorCount = 0;
				return $this->FormatMessage($anError);
			}
			$this->SessionHandler('destroy');
	        return;
	    }
		
		// 1.4.0 sicurezza: nuovo ID di sessione dopo l'autenticazione (session fixation)
		if (session_status() === PHP_SESSION_ACTIVE && !headers_sent())
		{
			session_regenerate_id(true);
		}
		$CurrentSessionID = session_id();

	    if(!isset($_SESSION['webValidated']))
		{
	        $isNowWebValidated = $modx->db->query("UPDATE ".$web_user_attributes." SET `failedlogincount` = 0, `logincount` = `logincount` + 1, `lastlogin` = `thislogin`, `thislogin` = ".time().", `sessionid` = '".$CurrentSessionID."' where internalKey='".$this->User['internalKey']."'");
	    }
		// Flag the account as "Activated" by deleting the timestamp in `cachepwd`
		$cacheTimestamp = $modx->db->query("UPDATE ".$web_users." SET `cachepwd`='' WHERE `id`='".$this->User['internalKey']."'");
 	}
	
	
	/**
	 * UserDocumentGroups
	 * Find the web groups that this user is a member of.
	 *
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function UserDocumentGroups()
	{
		global $modx;
		
		if (!empty($this->Report))
		{
			return; //There was an error in the last step
		}
		
		$web_groups = $modx->getFullTableName('web_groups');
		$webgroup_access = $modx->getFullTableName('webgroup_access');
		
		$documentGroups = [];
		$i = 0;
	    $sql = "SELECT uga.documentgroup FROM ".$web_groups." ug INNER JOIN ".$webgroup_access." uga ON uga.webgroup=ug.webgroup WHERE ug.webuser =".$this->User['internalKey'];
	    $currentUsersGroups = $modx->db->query($sql); 
	    while ($row = $modx->db->getRow($currentUsersGroups,'num')) $documentGroups[$i++] = $row[0];
	    $_SESSION['webDocgroups'] = $documentGroups;
	}
	
	/**
	 *  UserWebGroups
	 *  Find the web groups that this user is a member of (NB: the above function actually gets the document groups)
	 *
	 *  (TS)
	 */
	function UserWebGroups()
		{
		global $modx;

		$web_groups = $modx->getFullTableName('web_groups');
		$webgroup_names = $modx->getFullTableName('webgroup_names');

		$currentUsersWebGroups = $modx->db->query("SELECT {$webgroup_names}.* FROM {$web_groups}, {$webgroup_names} WHERE {$webgroup_names}.id = {$web_groups}.webgroup AND webuser = ".$this->User['internalKey']);

		$_SESSION['webUserGroupNames'] = array();
		while($row = $modx->db->getRow($currentUsersWebGroups)) $_SESSION['webUserGroupNames'][$row['id']] = $row['name'];
		}			

	
	
	/**
	 * LoginHomePage
	 * Redirect user to specified login page ($this->liHomeId).
	 * $this->liHomeId is an array, each document ID is queried.
	 * The user is redirected to the first page that they have permission to view.
	 * 
	 * If $this->liHomeId is empty, refresh the current page.
	 *
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function LoginHomePage()
	{
		global $modx;
		
		if (!empty($this->Report))
		{
			return; //There was an error in the last step
		}
		
		if (!empty($this->liHomeId))
		{
			if (is_array($this->liHomeId))
			{
				foreach($this->liHomeId as $id)
				{
		            $id = trim($id);
		            if ($modx->getPageInfo($id))
						{
							$url = $modx->makeURL($id);
					        $modx->sendRedirect($url,0,'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
					        return;
						}
		        }
			}
			else 
			{
				$url = $modx->makeURL($this->loHomeId);
		        $modx->sendRedirect($url,0,'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
		        return;
			}
		}
		else
		{
			$url = $modx->makeURL($modx->documentIdentifier);
	        $modx->sendRedirect($url,0,'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
		}
		return;
	}
	
	
	/**
	 * LogoutHomePage
	 * Redirect user to specified logout page ($this->loHomeId).
	 * If $this->loHomeId is empty, refresh the current page.
	 *
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function LogoutHomePage()
	{
		global $modx;
		
		if (!empty($this->Report))
		{
			return; //There was an error in the last step
		}
		
		if (!empty($this->loHomeId))
		{
			$url = $modx->makeURL($this->loHomeId);
	        $modx->sendRedirect($url,0,'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
	        return;
		}
		else
		{
			$url = $modx->makeURL($modx->documentIdentifier);
	        $modx->sendRedirect($url,0,'REDIRECT_HEADER'); // CREDIT: Guillaume to redirect directely
		}
		return;
	}
	
	
	/**
	 * SessionHandler
	 * Starts the user session on login success. Destroys session on error or logout.
	 *
	 * @param string $directive ('start' or 'destroy')
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function SessionHandler($directive)
	{
		global $modx;
				
		if (!empty($this->Report))
		{
			return; //There was an error in the last step
		}
		
		if ($directive == 'start') 
		{
			$_SESSION['webShortname'] = $this->Username; 
		    $_SESSION['webFullname'] = $this->User['fullname']; 
		    $_SESSION['webEmail'] = $this->User['email']; 
		    $_SESSION['webValidated'] = 1; 
		    $_SESSION['webInternalKey'] = $this->User['internalKey']; 
		    $_SESSION['webValid'] = ''; // 1.4.0: la password non viene piu' conservata in sessione
		    $_SESSION['webUser'] = base64_encode((string)$this->Username); 
		    $_SESSION['webFailedlogins'] = $this->User['failedlogincount']; 
		    $_SESSION['webLastlogin'] = $this->User['lastlogin']; 
		    $_SESSION['webnrlogins'] = $this->User['logincount'];
		    $this->UserWebGroups(); // (TS)

			// 1.4.0: "ricordami" con token casuale (il cookie non contiene piu' l'hash della password)
			$rememberExpires = 0;
			if ($this->RememberExpires > time())
			{
				$rememberExpires = $this->RememberExpires; // rinnovo dopo un login automatico
			}
			elseif ($this->PostValue('rememberme') == 'on')
			{
				$rememberExpires = time() + (60 * 60 * 24 * max(1, (int)$this->RememberDays));
			}
			elseif (isset($_POST['stayloggedin']) && (int)$_POST['stayloggedin'] > 0)
			{
				$rememberExpires = time() + (int)$_POST['stayloggedin'];
			}
			if ($rememberExpires > time() && is_array($this->User) && !empty($this->User['internalKey']))
			{
				$this->CreateRememberToken($this->User['internalKey'], $rememberExpires);
			}
			$this->RememberExpires = 0;
		}
		
		if ($directive == 'destroy')
		{
			// if we were launched from the manager do NOT destroy session !!!
	        if (isset($_SESSION['mgrValidated']))
			{
	            unset($_SESSION['webShortname']);
	            unset($_SESSION['webFullname']);
	            unset($_SESSION['webEmail']);
	            unset($_SESSION['webValidated']);
	            unset($_SESSION['webInternalKey']);
	            unset($_SESSION['webValid']);
	            unset($_SESSION['webUser']);
	            unset($_SESSION['webFailedlogins']);
	            unset($_SESSION['webLastlogin']);
	            unset($_SESSION['webnrlogins']);
	            unset($_SESSION['webUsrConfigSet']);
	            unset($_SESSION['webUserGroupNames']);
	            unset($_SESSION['webDocgroups']);   
	
				$this->ClearLoginCookie(); // 1.4.0
	        }
	        else
			{
	            if (isset($_COOKIE[session_name()]))
				{
	                setcookie(session_name(), '', time() - 3600, $modx->config['base_url']); // 1.4.0: con scadenza 0 il cookie non veniva cancellato
	            }
				
				$this->ClearLoginCookie(); // 1.4.0
	            session_destroy();
	        }
		}
	}
	
	
	/**
	 * Set timestamp in `active_users`.`lasthit` to current time.
	 *
	 * @return void
	 * @access public
	 * @author Scotty Delicious
	 */
	function ActiveUsers()
	{
		global $modx;
		
		if (!$modx->getLoginUserID() || !empty($this->Report))
		{
			return;
		}
		$CurrentUser = $modx->getWebUserInfo($modx->getLoginUserID());
		
		if (!empty($_SERVER['HTTP_X_FORWARD_FOR']))
		{
			$ip = $_SERVER['HTTP_X_FORWARD_FOR'];
		}
		else
		{
			$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		}
		
		$active_users = $modx->getFullTableName('active_users');
		$activityCheck = "SELECT * FROM ".$active_users." WHERE `internalKey` = '-".$CurrentUser['internalKey']."'";
		$IamActive = $modx->db->query($activityCheck);
		$limit = $modx->db->getRecordCount($IamActive);
		if ($limit == 0)
		{
			$makeMeActive = $modx->db->query("INSERT INTO ".$active_users." (`internalKey`,`username`,`lasthit`,`id`,`action`,`ip`) VALUES ('-".$CurrentUser['internalKey']."','".$CurrentUser['username']."','".time()."','0','998','".$ip."')");
		}
		else
		{
			$updateActivity = $modx->db->query("UPDATE ".$active_users." SET `lasthit` = '".time()."', `ip` = '".$ip."' WHERE `internalKey` = '-".$CurrentUser['internalKey']."'");
		}
		
	}
	
	/**
	 * Set timestamp in `active_users` table to 0.
	 *
	 * @return void
	 * @access protected
	 * @author Scotty Delicious
	 */
	function StatusToOffline()
	{
		global $modx;
		$CurrentUser = $modx->getWebUserInfo($modx->getLoginUserID());
		if (!is_array($CurrentUser))
		{
			return;
		}
		$active_users = $modx->getFullTableName('active_users');
		$IamOffline = $modx->db->query("UPDATE ".$active_users." SET `lasthit` = '0' WHERE `internalKey` = '-".$CurrentUser['internalKey']."'");
	}
	
	
	/**
	 * QueryDbForUser
	 * Queries the web_users table for $_POST['username'].
	 *
	 * @param string $Username The username of the user to query for.
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function QueryDbForUser($Username)
	{
		global $modx;
		
		$Username = (string)$Username; // FIX PHP 8.1
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		$query = "SELECT * FROM ".$web_users.", ".$web_user_attributes.", ".$this->CustomTable." WHERE BINARY LOWER(".$web_users.".username) = '".strtolower($Username)."' AND ".$web_user_attributes.".`internalKey` = ".$web_users.".`id` AND ".$this->CustomTable.".`internalKey` = ".$web_users.".`id`";
		$query2 = "SELECT * FROM ".$web_users.", ".$web_user_attributes.", ".$this->CustomTable." WHERE(".$web_users.".username) = '".$Username."' AND ".$web_user_attributes.".`internalKey` = ".$web_users.".`id` AND ".$this->CustomTable.".`internalKey` = ".$web_users.".`id`";
		if (!$limit = $modx->db->getRecordCount($dataSource = $modx->db->query($query))) $limit = $modx->db->getRecordCount($dataSource = $modx->db->query($query2));
		
		if ($limit == 0)
		{
			$query = "SELECT * FROM ".$web_users.", ".$web_user_attributes." WHERE BINARY LOWER(".$web_users.".username) = '".strtolower($Username)."' AND ".$web_user_attributes.".`internalKey` = ".$web_users.".`id`";
			$query2 = "SELECT * FROM ".$web_users.", ".$web_user_attributes." WHERE(".$web_users.".username) = '".$Username."' AND ".$web_user_attributes.".`internalKey` = ".$web_users.".`id`";
			if (!$limit = $modx->db->getRecordCount($dataSource = $modx->db->query($query))) $limit = $modx->db->getRecordCount($dataSource = $modx->db->query($query2));
		}
		
		if ($limit == 0 || $limit > 1)
		{
			$this->User = false;
			return false;
		}
		else
		{
			return $modx->db->getRow($dataSource);
		}
	}
	
	
	/**
	 * UserIsBlocked
	 * Queries the web_user_attributes table to see if this user should
	 * be blocked. If the user IS blocked, prevent them from logging in.
	 *
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function UserIsBlocked()
	{
		global $modx;
		
		if (!empty($this->Report))
		{
			return; //There was an error in the last step
		}
		
		$web_users = $modx->getFullTableName('web_users');
		$web_user_attributes = $modx->getFullTableName('web_user_attributes');
		
		if ($this->User['failedlogincount'] >= $modx->config['failed_login_attempts'] && $this->User['blockeduntil'] > time())
		{
	        $this->SessionHandler('destroy');
	        return $this->FormatMessage($this->LanguageArray[22]);
	    }
	
		if ($this->User['failedlogincount'] >= $modx->config['failed_login_attempts'] && $this->User['blockeduntil'] < time())
		{// blocked due to number of login errors, but get to try again
	        $sql = "UPDATE ".$web_user_attributes." SET failedlogincount='0', blockeduntil='".(time()-1)."' where internalKey=".$this->User['internalKey'];
	        $updateFailedLoginCount = $modx->db->query($sql);
			return;
	    }
		
		if ($this->User['blocked'] == "1")
		{ // this user has been blocked by an admin, so no way he's loggin in!
	        $this->SessionHandler('destroy');
	        return $this->FormatMessage($this->LanguageArray[23]);
	    }
			
		if ($this->User['blockeduntil'] >= time())
		{ // this user has a block until date
			$blockedUntilTime = $this->User['blockeduntil'] - time();
			$UserIsBlockedUntil = $blockedUntilTime / 60;
			$blockedMinutes = substr($UserIsBlockedUntil, 0, strpos($UserIsBlockedUntil, "."));
			
			$this->SessionHandler('destroy');
			$anError = str_replace('[+000+]', $blockedMinutes, $this->LanguageArray[24]);
			return $this->FormatMessage($anError);
		}
	
		if($this->User['blockedafter'] > 0 && $this->User['blockedafter'] < time())
		{ // this user has a block after date
	        $this->SessionHandler('destroy');
	        return $this->FormatMessage($this->LanguageArray[23]);
	    }
	
		if (isset($modx->config['allowed_ip']))
		{
	        if (strpos($modx->config['allowed_ip'],$_SERVER['REMOTE_ADDR'])===false)
			{
	            return $this->FormatMessage($this->LanguageArray[25]);
	        }
	    }
	
		if (isset($modx->config['allowed_days']))
		{
	        $date = getdate();
	        $day = $date['wday']+1;
	        if (strpos($modx->config['allowed_days'], (string)$day) === false)
			{
	            return $this->FormatMessage($this->LanguageArray[26]);
	        }        
	    }
		
	}
	
	
	/**
	 * MakeDateForDb
	 * Returns a UNIX timestamp for the string provided.
	 *
	 * @param string $date A date in the format MM-DD-YYY
	 * @return int Returns a UNIX timestamp for the date provided.
	 * @author Scotty Delicious
	 */
	function MakeDateForDb($date)
	{
		// $date is a string like 01-22-1975.
		if (strpos($date, '-'))
		{
			$dateArray = explode('-', $date);
		}
		else if (strpos($date, '/'))
		{
			$dateArray = explode('/', $date);
		}
		else
		{
			return $this->FormatMessage($this->LanguageArray[27]);
		}
		
		$dateArray = explode('-', $date);
		// $dateArray is somethink like [0]=01, [1]=22, [2]=1975
		// make a unix timestamp out of the original date string.
		$timestamp = mktime(0, 0, 0, $dateArray[0], $dateArray[1], $dateArray[2]);
		return $timestamp;
	}
	
	
	/**
	 * Password (1.4.0)
	 * Di default MD5, compatibile con il core di Evolution 1.4 e con MODxAPI
	 * (FormLister, DocLister...). Con &passwordHash=`bcrypt` le password vengono
	 * salvate con password_hash() e le MD5 esistenti convertite al primo login.
	 * La verifica accetta sempre entrambi i formati.
	 */
	function ColumnLength($table, $column)
	{
		global $modx;
		$key = $table.'.'.$column;
		if (!isset($this->ColumnCache[$key]))
		{
			$this->ColumnCache[$key] = 0;
			$rs = $modx->db->query("SHOW COLUMNS FROM ".$modx->getFullTableName($table)." LIKE '".$modx->db->escape($column)."'");
			$row = $rs ? $modx->db->getRow($rs) : false;
			if (is_array($row) && isset($row['Type']))
			{
				if (preg_match('/\((\d+)\)/', $row['Type'], $m))
				{
					$this->ColumnCache[$key] = (int)$m[1];
				}
				elseif (stripos($row['Type'], 'text') !== false)
				{
					$this->ColumnCache[$key] = 65535;
				}
			}
		}
		return $this->ColumnCache[$key];
	}
	
	function UseModernHash()
	{
		return $this->ModernHash && $this->ColumnLength('web_users', 'password') >= 60;
	}
	
	function HashPassword($plain)
	{
		return $this->UseModernHash() ? password_hash((string)$plain, PASSWORD_BCRYPT) : md5((string)$plain);
	}
	
	function VerifyPassword($plain, $stored)
	{
		$stored = (string)$stored;
		if ($stored === '')
		{
			return false;
		}
		if (strpos($stored, '$2y$') === 0)
		{
			return password_verify((string)$plain, $stored);
		}
		return hash_equals(strtolower($stored), md5((string)$plain));
	}
	
	function PasswordNeedsUpgrade($stored)
	{
		if (!$this->UseModernHash())
		{
			return false;
		}
		return strpos((string)$stored, '$2y$') !== 0 || password_needs_rehash($stored, PASSWORD_BCRYPT);
	}
	
	// Password temporanea del reset (campo cachepwd): hash se la colonna e' abbastanza lunga
	function HashTempPassword($plain)
	{
		if ($this->ColumnLength('web_users', 'cachepwd') >= 72)
		{
			return password_hash((string)$plain, PASSWORD_BCRYPT);
		}
		return (string)$plain;
	}
	
	function VerifyTempPassword($plain, $stored)
	{
		$stored = (string)$stored;
		if (strpos($stored, '$2y$') === 0)
		{
			return password_verify((string)$plain, $stored);
		}
		return $stored !== '' && hash_equals($stored, (string)$plain);
	}
	
	
	/**
	 * Token "ricordami" (1.4.0)
	 * Cookie "selettore:validatore" casuale; in web_user_settings viene salvato solo
	 * l'hash SHA-256 del validatore con la scadenza. Il cookie non contiene piu'
	 * l'hash della password.
	 */
	function CreateRememberToken($internalKey, $expires)
	{
		global $modx;
		$internalKey = (int)$internalKey;
		if ($internalKey < 1)
		{
			return;
		}
		$this->PurgeRememberTokens($internalKey, false);
		$selector = bin2hex(random_bytes(6));
		$validator = bin2hex(random_bytes(32));
		$modx->db->query("INSERT INTO ".$modx->getFullTableName('web_user_settings')." (`webuser`, `setting_name`, `setting_value`) VALUES (".$internalKey.", 'wlpe_rt_".$selector."', '".(int)$expires.":".hash('sha256', $validator)."')");
		$this->SetLoginCookie($selector.':'.$validator, $expires);
	}
	
	// $all = true: tutti i token dell'utente (cambio password); false: solo quelli scaduti
	function PurgeRememberTokens($internalKey, $all)
	{
		global $modx;
		$table = $modx->getFullTableName('web_user_settings');
		$internalKey = (int)$internalKey;
		if ($all)
		{
			$modx->db->query("DELETE FROM ".$table." WHERE `webuser` = ".$internalKey." AND LEFT(`setting_name`, 8) = 'wlpe_rt_'");
			return;
		}
		$rs = $modx->db->query("SELECT `setting_name`, `setting_value` FROM ".$table." WHERE `webuser` = ".$internalKey." AND LEFT(`setting_name`, 8) = 'wlpe_rt_'");
		while ($rs && ($row = $modx->db->getRow($rs)))
		{
			$parts = explode(':', (string)$row['setting_value'], 2);
			if ((int)$parts[0] < time())
			{
				$modx->db->query("DELETE FROM ".$table." WHERE `webuser` = ".$internalKey." AND `setting_name` = '".$modx->db->escape($row['setting_name'])."'");
			}
		}
	}
	
	// Selettore valido dal cookie corrente, oppure '' 
	function RememberCookieParts()
	{
		if (!empty($_COOKIE[$this->CookieName]) && is_string($_COOKIE[$this->CookieName]) && preg_match('/^([a-f0-9]{12}):([a-f0-9]{64})$/', $_COOKIE[$this->CookieName], $m))
		{
			return array($m[1], $m[2]);
		}
		return array('', '');
	}
	
	
	/**
	 * CSRF (1.4.0)
	 * Il token e' sempre disponibile nei segnaposto [+form.csrf+] e [+wlpe.csrf+];
	 * viene verificato solo con &csrf=`1`.
	 */
	function CsrfToken()
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			return '';
		}
		if (empty($_SESSION['wlpe_csrf']) || !is_string($_SESSION['wlpe_csrf']))
		{
			$_SESSION['wlpe_csrf'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['wlpe_csrf'];
	}
	
	function CheckCsrfToken()
	{
		$sent = $this->PostString('wlpe_csrf');
		return $sent !== '' && isset($_SESSION['wlpe_csrf']) && is_string($_SESSION['wlpe_csrf']) && hash_equals($_SESSION['wlpe_csrf'], $sent);
	}
	
	function CsrfErrorMessage()
	{
		return isset($this->LanguageArray[43]) ? $this->LanguageArray[43] : 'The form has expired. Please reload the page and try again.';
	}
	
	
	/**
	 * EscapeOutput (1.4.0)
	 * Escape HTML dei valori mostrati nei segnaposto, senza ricodificare le entita'
	 * gia' presenti nei dati salvati, e neutralizzazione dei tag di Evolution.
	 */
	function EscapeOutput($value)
	{
		global $modx;
		if (is_array($value))
		{
			$value = implode(', ', $value);
		}
		$charset = !empty($modx->config['modx_charset']) ? $modx->config['modx_charset'] : 'UTF-8';
		$value = htmlspecialchars((string)$value, ENT_QUOTES, $charset, false);
		return str_replace(array('[', ']', '{', '}', '`'), array('&#91;', '&#93;', '&#123;', '&#125;', '&#96;'), $value);
	}
	
	
	/**
	 * IsHttps (1.4.0)
	 */
	function IsHttps()
	{
		global $modx;
		if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
		{
			return true;
		}
		if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
		{
			return true;
		}
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
		{
			return true;
		}
		return isset($modx->config['server_protocol']) && $modx->config['server_protocol'] === 'https';
	}
	
	
	/**
	 * SetLoginCookie / ClearLoginCookie (1.4.0)
	 * Cookie "ricordami" con HttpOnly, SameSite=Lax e Secure su HTTPS.
	 */
	function SetLoginCookie($value, $expires)
	{
		setcookie($this->CookieName, $value, array(
			'expires'  => (int)$expires,
			'path'     => '/',
			'secure'   => $this->IsHttps(),
			'httponly' => true,
			'samesite' => 'Lax'
		));
	}
	
	function ClearLoginCookie()
	{
		global $modx;
		list($selector) = $this->RememberCookieParts();
		if ($selector !== '')
		{
			$modx->db->query("DELETE FROM ".$modx->getFullTableName('web_user_settings')." WHERE `setting_name` = 'wlpe_rt_".$selector."'");
		}
		$past = time() - 3600;
		setcookie($this->CookieName, '', array(
			'expires'  => $past,
			'path'     => '/',
			'secure'   => $this->IsHttps(),
			'httponly' => true,
			'samesite' => 'Lax'
		));
	}
	
	
	/**
	 * SetDobFormat / DobStrftimeFormat (1.4.0)
	 * Formato della data di nascita: d, m, Y separati da - / oppure . (es. d-m-Y)
	 */
	function SetDobFormat($format)
	{
		if (preg_match('#^([dmY])([-/.])([dmY])\2([dmY])$#', (string)$format, $m) && count(array_unique(array($m[1], $m[3], $m[4]))) == 3)
		{
			$this->DobFormat = $format;
		}
	}
	
	function DobStrftimeFormat()
	{
		return str_replace(array('d', 'm', 'Y'), array('%d', '%m', '%Y'), $this->DobFormat);
	}
	
	
	/**
	 * IsUploadedFile (1.4.0) - separato per poterlo sostituire nei test
	 */
	function IsUploadedFile($file)
	{
		return is_uploaded_file($file);
	}
	
	
	/**
	 * FieldSubmitted (FIX issue #5)
	 * True se il campo e' stato inviato dal form. Le checkbox definite in
	 * &inputHandler contano sempre come inviate (non spuntate il browser non le invia),
	 * la foto solo se e' stato caricato un file.
	 */
	function FieldSubmitted($field)
	{
		if ($field == 'photo')
		{
			return $this->AllowPhoto && !empty($_FILES['photo']['name']);
		}
		return isset($_POST[$field]) || isset($this->CheckboxFields[$field]);
	}
	
	
	/**
	 * PostValue / PostString (FIX PHP 8)
	 * Lettura di $_POST senza warning "Undefined array key".
	 */
	function PostValue($key, $default = '')
	{
		return isset($_POST[$key]) ? $_POST[$key] : $default;
	}
	
	function PostString($key)
	{
		$value = $this->PostValue($key, '');
		if (is_array($value))
		{
			$value = implode('||', $value);
		}
		return (string)$value;
	}
	
	
	/**
	 * FormatDate (FIX PHP 8.1+)
	 * Sostituisce strftime(), deprecata da PHP 8.1. Su PHP < 8.1 usa strftime()
	 * come prima; da 8.1 converte il formato strftime: i nomi di giorni e mesi
	 * escono nella lingua del locale (setlocale LC_TIME) se c'e' l'estensione intl,
	 * altrimenti in inglese come strftime() con locale "C".
	 *
	 * @param string $format Formato strftime (es. '%d/%m/%Y')
	 * @param int $timestamp
	 * @return string
	 */
	function FormatDate($format, $timestamp)
	{
		$timestamp = (int)$timestamp;
		if (PHP_VERSION_ID < 80100 && function_exists('strftime'))
		{
			return strftime($format, $timestamp);
		}
		
		$locale = setlocale(LC_TIME, 0);
		$useIntl = class_exists('IntlDateFormatter') && $locale && $locale !== 'C' && $locale !== 'POSIX';
		if ($useIntl)
		{
			$locale = preg_replace('/[.@].*$/', '', $locale);
		}
		
		return preg_replace_callback('/%([a-zA-Z%])/', function ($m) use ($timestamp, $useIntl, $locale)
		{
			$intlPatterns = array('a' => 'EEE', 'A' => 'EEEE', 'b' => 'MMM', 'h' => 'MMM', 'B' => 'MMMM');
			if ($useIntl && isset($intlPatterns[$m[1]]))
			{
				$fmt = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), IntlDateFormatter::GREGORIAN, $intlPatterns[$m[1]]);
				$out = $fmt->format($timestamp);
				if ($out !== false)
				{
					return $out;
				}
			}
			switch ($m[1])
			{
				case 'a': return date('D', $timestamp);
				case 'A': return date('l', $timestamp);
				case 'b':
				case 'h': return date('M', $timestamp);
				case 'B': return date('F', $timestamp);
				case 'C': return sprintf('%02d', floor(date('Y', $timestamp) / 100));
				case 'd': return date('d', $timestamp);
				case 'D': return date('m/d/y', $timestamp);
				case 'e': return sprintf('%2d', date('j', $timestamp));
				case 'F': return date('Y-m-d', $timestamp);
				case 'G': return date('o', $timestamp);
				case 'g': return date('y', $timestamp);
				case 'H': return date('H', $timestamp);
				case 'I': return date('h', $timestamp);
				case 'j': return sprintf('%03d', date('z', $timestamp) + 1);
				case 'k': return sprintf('%2d', date('G', $timestamp));
				case 'l': return sprintf('%2d', date('g', $timestamp));
				case 'm': return date('m', $timestamp);
				case 'M': return date('i', $timestamp);
				case 'n': return "\n";
				case 'p': return date('A', $timestamp);
				case 'P': return date('a', $timestamp);
				case 'r': return date('h:i:s A', $timestamp);
				case 'R': return date('H:i', $timestamp);
				case 's': return (string)$timestamp;
				case 'S': return date('s', $timestamp);
				case 't': return "\t";
				case 'T': return date('H:i:s', $timestamp);
				case 'u': return date('N', $timestamp);
				case 'V': return date('W', $timestamp);
				case 'w': return date('w', $timestamp);
				case 'y': return date('y', $timestamp);
				case 'Y': return date('Y', $timestamp);
				case 'z': return date('O', $timestamp);
				case 'Z': return date('T', $timestamp);
				case '%': return '%';
			}
			return $m[0];
		}, $format);
	}
	
	
	/**
	 * AgeFromTimestamp (FIX)
	 * Eta' in anni da un timestamp di nascita; stringa vuota se non impostata.
	 */
	function AgeFromTimestamp($dob)
	{
		$dob = (int)$dob;
		if ($dob == 0)
		{
			return '';
		}
		$birth = new DateTime('@'.$dob);
		$birth->setTimezone(new DateTimeZone(date_default_timezone_get()));
		return (string)$birth->diff(new DateTime('now'))->y;
	}
	
	
	/**
	 * DobToInt (FIX strict mode)
	 * Converte la data di nascita in timestamp intero per la colonna INT `dob`.
	 * Accetta MM-DD-YYYY, MM/DD/YYYY, MM.DD.YYYY (stesso formato di MakeDateForDb)
	 * oppure un timestamp gia' numerico. Vuoto o non valido => 0.
	 *
	 * @param string $date
	 * @return int
	 */
	function DobToInt($date)
	{
		$date = trim((string)$date);
		if ($date === '')
		{
			return 0;
		}
		if (preg_match('/^-?\d+$/', $date))
		{
			return (int)$date;
		}
		$parts = preg_split('#[-/.]#', $date);
		if (count($parts) != 3)
		{
			return 0;
		}
		// 1.4.0: ordine secondo &dobFormat; AAAA-MM-GG (input type="date") riconosciuto sempre
		if (strlen(trim($parts[0])) == 4)
		{
			$order = array('Y', 'm', 'd');
		}
		else
		{
			$order = preg_split('#[-/.]#', $this->DobFormat);
		}
		$values = array_combine($order, array_map('intval', $parts));
		$m = $values['m'];
		$d = $values['d'];
		$y = $values['Y'];
		if (!checkdate($m, $d, $y))
		{
			return 0;
		}
		return (int)mktime(0, 0, 0, $m, $d, $y);
	}


	/**
	 * CreateUserImage
	 * Creates a 100px by 100px image for the user profile from a user uploaded image.
	 * This image is renamed to the username and moved to the webloginpe/userimages/ folder.
	 * The URL to this image is returned to be stored in the web_user_attributes table.
	 *
	 * @return string A URL to the user image created.
	 * @author Scotty Delicious
	 */
	function CreateUserImage()
	{
		global $modx;
		
		// 1.4.0 sicurezza: il file caricato non viene mai spostato nella root del sito,
		// il tipo viene verificato sul contenuto reale (non sul MIME dichiarato dal browser),
		// il nome del file e' generato dallo username e l'immagine viene sempre ricodificata.
		$imageAttributes = array_pad(explode(',', str_replace(' ', '', (string)$this->UserImageSettings)), 3, 0);
		$maxSize = (int)$imageAttributes[0];
		$maxWidth = max(1, (int)$imageAttributes[1]);
		$maxHeight = max(1, (int)$imageAttributes[2]);
		
		if (!isset($_FILES['photo']['tmp_name'], $_FILES['photo']['error']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK || !$this->IsUploadedFile($_FILES['photo']['tmp_name']))
		{
			return $this->FormatMessage($this->LanguageArray[29]);
		}
		
		if ($maxSize > 0 && $_FILES['photo']['size'] >= $maxSize)
		{
			$sizeInKb = round($maxSize / 1024);
			$sizeError = str_replace('[+000+]', $sizeInKb, $this->LanguageArray[28]);
			return $this->FormatMessage($sizeError);
		}
		
		$tmpFile = $_FILES['photo']['tmp_name'];
		$info = @getimagesize($tmpFile);
		$extensions = array(IMAGETYPE_JPEG => '.jpg', IMAGETYPE_GIF => '.gif', IMAGETYPE_PNG => '.png');
		if ($info === false || !isset($extensions[$info[2]]) || $info[0] < 1 || $info[1] < 1 || ($info[0] * $info[1]) > 40000000)
		{
			return $this->FormatMessage($this->LanguageArray[30]);
		}
		if (!function_exists('imagecreatetruecolor'))
		{
			return $this->FormatMessage($this->LanguageArray[29]);
		}
		$width = (int)$info[0];
		$height = (int)$info[1];
		$imageType = $info[2];
		$ext = $extensions[$imageType];
		
		// License and registration ma'am. I need to se an ID!
		$username = '';
		if ($modx->getLoginUserID())
		{
			$currentWebUser = $modx->getWebUserInfo($modx->getLoginUserID());
			$username = is_array($currentWebUser) ? $currentWebUser['username'] : '';
			if ($this->Type == 'manager')
			{
				$username = $this->PostString('username');
			}
		}
		else
		{
			$username = ($this->Username != '') ? $this->Username : $this->PostString('username');
		}
		$fileBase = preg_replace('/[^a-z0-9_-]/', '', str_replace(' ', '_', strtolower((string)$username)));
		if ($fileBase === '')
		{
			$fileBase = 'user_'.md5(uniqid('', true));
		}
		
		switch ($imageType)
		{
			case IMAGETYPE_JPEG: $image = @imagecreatefromjpeg($tmpFile); break;
			case IMAGETYPE_GIF:  $image = @imagecreatefromgif($tmpFile);  break;
			default:             $image = @imagecreatefrompng($tmpFile);  break;
		}
		if (!$image)
		{
			return $this->FormatMessage($this->LanguageArray[30]);
		}
		
		// Ridimensiona senza ingrandire
		$newWidth = $width;
		$newHeight = $height;
		$wm = $width / $maxWidth;
		$hm = $height / $maxHeight;
		if ($wm > 1 || $hm > 1)
		{
			if ($wm > $hm)
			{
				$newWidth = $maxWidth;
				$newHeight = $height / $wm;
			}
			else
			{
				$newHeight = $maxHeight;
				$newWidth = $width / $hm;
			}
		}
		$newWidth = max(1, (int)round($newWidth));
		$newHeight = max(1, (int)round($newHeight));
		
		$image_p = imagecreatetruecolor($newWidth, $newHeight);
		if ($imageType != IMAGETYPE_JPEG)
		{
			imagealphablending($image_p, false);
			imagesavealpha($image_p, true);
			$trans = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
			imagefill($image_p, 0, 0, $trans);
		}
		imagecopyresampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
		
		$relativeDir = 'assets/snippets/webloginpe/userimages/';
		$filePath = $modx->config['base_path'].$relativeDir.$fileBase.$ext;
		switch ($imageType)
		{
			case IMAGETYPE_JPEG: $saved = imagejpeg($image_p, $filePath, 90); break;
			case IMAGETYPE_GIF:  $saved = imagegif($image_p, $filePath);      break;
			default:             $saved = imagepng($image_p, $filePath, 6);   break;
		}
		if (!$saved)
		{
			return $this->FormatMessage($this->LanguageArray[29]);
		}
		
		// Rimuove una eventuale foto precedente con estensione diversa
		foreach ($extensions as $otherExt)
		{
			$otherFile = $modx->config['base_path'].$relativeDir.$fileBase.$otherExt;
			if ($otherExt !== $ext && is_file($otherFile))
			{
				@unlink($otherFile);
			}
		}
		
		return $relativeDir.$fileBase.$ext;
	}
	
	
	/**
	 * StringForGenderInt
	 * Returns a string ('Male', 'Female', or 'Unknown') for the integer $genderInt (integer stored in web_user_attributes).
	 *
	 * @param int $genderInt (0, 1, or 2)
	 * @return string (0 = 'Unknown', 1 = 'Male', 2 = 'Female') 
	 * @author Scotty Delicious
	 */
	function StringForGenderInt($genderInt)
	{
		if ($genderInt == 1)
		{
			return 'Male';
		}
		else if ($genderInt == 2)
		{
			return 'Female';
		}
		else
		{
			return 'Unknown';
		}
		
	}
	
	
	/**
	 * StringForCountryInt
	 * Returns a string (the name of the country) for the integer $countryInt (integer stored in web_user_attributes).
	 *
	 * @param int $countryInt 
	 * @return string The name of the country
	 * @author Scotty Delicious
	 */
	function StringForCountryInt($countryInt)
	{
		switch ($countryInt)
		{
			case "1" : return 'Afghanistan'; break;
            case "2" : return 'Albania'; break;
            case "3" : return 'Algeria'; break;
            case "4" : return 'American Samoa'; break;
            case "5" : return 'Andorra'; break;
            case "6" : return 'Angola'; break;
            case "7" : return 'Anguilla'; break;
            case "8" : return 'Antarctica'; break;
            case "9" : return 'Antigua and Barbuda'; break;
            case "10" : return 'Argentina'; break;
            case "11" : return 'Armenia'; break;
            case "12" : return 'Aruba'; break;
            case "13" : return 'Australia'; break;
            case "14" : return 'Austria'; break;
            case "15" : return 'Azerbaijan'; break;
            case "16" : return 'Bahamas'; break;
            case "17" : return 'Bahrain'; break;
            case "18" : return 'Bangladesh'; break;
            case "19" : return 'Barbados'; break;
            case "20" : return 'Belarus'; break;
            case "21" : return 'Belgium'; break;
            case "22" : return 'Belize'; break;
            case "23" : return 'Benin'; break;
            case "24" : return 'Bermuda'; break;
            case "25" : return 'Bhutan'; break;
            case "26" : return 'Bolivia'; break;
            case "27" : return 'Bosnia and Herzegowina'; break;
            case "28" : return 'Botswana'; break;
            case "29" : return 'Bouvet Island'; break;
            case "30" : return 'Brazil'; break;
            case "31" : return 'British Indian Ocean Territory'; break;
            case "32" : return 'Brunei Darussalam'; break;
            case "33" : return 'Bulgaria'; break;
            case "34" : return 'Burkina Faso'; break;
            case "35" : return 'Burundi'; break;
            case "36" : return 'Cambodia'; break;
            case "37" : return 'Cameroon'; break;
            case "38" : return 'Canada'; break;
            case "39" : return 'Cape Verde'; break;
            case "40" : return 'Cayman Islands'; break;
            case "41" : return 'Central African Republic'; break;
            case "42" : return 'Chad'; break;
            case "43" : return 'Chile'; break;
            case "44" : return 'China'; break;
            case "45" : return 'Christmas Island'; break;
            case "46" : return 'Cocos (Keeling) Islands'; break;
            case "47" : return 'Colombia'; break;
            case "48" : return 'Comoros'; break;
            case "49" : return 'Congo'; break;
            case "50" : return 'Cook Islands'; break;
            case "51" : return 'Costa Rica'; break;
            case "52" : return 'Cote D&#39;Ivoire'; break;
            case "53" : return 'Croatia'; break;
            case "54" : return 'Cuba'; break;
            case "55" : return 'Cyprus'; break;
            case "56" : return 'Czech Republic'; break;
            case "57" : return 'Denmark'; break;
            case "58" : return 'Djibouti'; break;
            case "59" : return 'Dominica'; break;
            case "60" : return 'Dominican Republic'; break;
            case "61" : return 'East Timor'; break;
            case "62" : return 'Ecuador'; break;
            case "63" : return 'Egypt'; break;
            case "64" : return 'El Salvador'; break;
            case "65" : return 'Equatorial Guinea'; break;
            case "66" : return 'Eritrea'; break;
            case "67" : return 'Estonia'; break;
            case "68" : return 'Ethiopia'; break;
            case "69" : return 'Falkland Islands (Malvinas)'; break;
            case "70" : return 'Faroe Islands'; break;
            case "71" : return 'Fiji'; break;
            case "72" : return 'Finland'; break;
            case "73" : return 'France'; break;
            case "74" : return 'France, Metropolitan'; break;
            case "75" : return 'French Guiana'; break;
            case "76" : return 'French Polynesia'; break;
            case "77" : return 'French Southern Territories'; break;
            case "78" : return 'Gabon'; break;
            case "79" : return 'Gambia'; break;
            case "80" : return 'Georgia'; break;
            case "81" : return 'Germany'; break;
            case "82" : return 'Ghana'; break;
            case "83" : return 'Gibraltar'; break;
            case "84" : return 'Greece'; break;
            case "85" : return 'Greenland'; break;
            case "86" : return 'Grenada'; break;
            case "87" : return 'Guadeloupe'; break;
            case "88" : return 'Guam'; break;
            case "89" : return 'Guatemala'; break;
            case "90" : return 'Guinea'; break;
            case "91" : return 'Guinea-bissau'; break;
            case "92" : return 'Guyana'; break;
            case "93" : return 'Haiti'; break;
            case "94" : return 'Heard and Mc Donald Islands'; break;
            case "95" : return 'Honduras'; break;
            case "96" : return 'Hong Kong'; break;
            case "97" : return 'Hungary'; break;
            case "98" : return 'Iceland'; break;
            case "99" : return 'India'; break;
            case "100" : return 'Indonesia'; break;
            case "101" : return 'Iran (Islamic Republic of)'; break;
            case "102" : return 'Iraq'; break;
            case "103" : return 'Ireland'; break;
            case "104" : return 'Israel'; break;
            case "105" : return 'Italy'; break;
            case "106" : return 'Jamaica'; break;
            case "107" : return 'Japan'; break;
            case "108" : return 'Jordan'; break;
            case "109" : return 'Kazakhstan'; break;
            case "110" : return 'Kenya'; break;
            case "111" : return 'Kiribati'; break;
            case "112" : return 'Korea, Democratic People&#39;s Republic of'; break;
            case "113" : return 'Korea, Republic of'; break;
            case "114" : return 'Kuwait'; break;
            case "115" : return 'Kyrgyzstan'; break;
            case "116" : return 'Lao People&#39;s Democratic Republic'; break;
            case "117" : return 'Latvia'; break;
            case "118" : return 'Lebanon'; break;
            case "119" : return 'Lesotho'; break;
            case "120" : return 'Liberia'; break;
            case "121" : return 'Libyan Arab Jamahiriya'; break;
            case "122" : return 'Liechtenstein'; break;
            case "123" : return 'Lithuania'; break;
            case "124" : return 'Luxembourg'; break;
            case "125" : return 'Macau'; break;
            case "126" : return 'Macedonia, The Former Yugoslav Republic of'; break;
            case "127" : return 'Madagascar'; break;
            case "128" : return 'Malawi'; break;
            case "129" : return 'Malaysia'; break;
            case "130" : return 'Maldives'; break;
            case "131" : return 'Mali'; break;
            case "132" : return 'Malta'; break;
            case "133" : return 'Marshall Islands'; break;
            case "134" : return 'Martinique'; break;
            case "135" : return 'Mauritania'; break;
            case "136" : return 'Mauritius'; break;
            case "137" : return 'Mayotte'; break;
            case "138" : return 'Mexico'; break;
            case "139" : return 'Micronesia, Federated States of'; break;
            case "140" : return 'Moldova, Republic of'; break;
            case "141" : return 'Monaco'; break;
            case "142" : return 'Mongolia'; break;
            case "143" : return 'Montserrat'; break;
            case "144" : return 'Morocco'; break;
            case "145" : return 'Mozambique'; break;
            case "146" : return 'Myanmar'; break;
            case "147" : return 'Namibia'; break;
            case "148" : return 'Nauru'; break;
            case "149" : return 'Nepal'; break;
            case "150" : return 'Netherlands'; break;
            case "151" : return 'Netherlands Antilles'; break;
            case "152" : return 'New Caledonia'; break;
            case "153" : return 'New Zealand'; break;
            case "154" : return 'Nicaragua'; break;
            case "155" : return 'Niger'; break;
            case "156" : return 'Nigeria'; break;
            case "157" : return 'Niue'; break;
            case "158" : return 'Norfolk Island'; break;
            case "159" : return 'Northern Mariana Islands'; break;
            case "160" : return 'Norway'; break;
            case "161" : return 'Oman'; break;
            case "162" : return 'Pakistan'; break;
            case "163" : return 'Palau'; break;
            case "164" : return 'Panama'; break;
            case "165" : return 'Papua New Guinea'; break;
            case "166" : return 'Paraguay'; break;
            case "167" : return 'Peru'; break;
            case "168" : return 'Philippines'; break;
            case "169" : return 'Pitcairn'; break;
            case "170" : return 'Poland'; break;
            case "171" : return 'Portugal'; break;
            case "172" : return 'Puerto Rico'; break;
            case "173" : return 'Qatar'; break;
            case "174" : return 'Reunion'; break;
            case "175" : return 'Romania'; break;
            case "176" : return 'Russian Federation'; break;
            case "177" : return 'Rwanda'; break;
            case "178" : return 'Saint Kitts and Nevis'; break;
            case "179" : return 'Saint Lucia'; break;
            case "180" : return 'Saint Vincent and the Grenadines'; break;
            case "181" : return 'Samoa'; break;
            case "182" : return 'San Marino'; break;
            case "183" : return 'Sao Tome and Principe'; break;
            case "184" : return 'Saudi Arabia'; break;
            case "185" : return 'Senegal'; break;
            case "186" : return 'Seychelles'; break;
            case "187" : return 'Sierra Leone'; break;
            case "188" : return 'Singapore'; break;
            case "189" : return 'Slovakia (Slovak Republic)'; break;
            case "190" : return 'Slovenia'; break;
            case "191" : return 'Solomon Islands'; break;
            case "192" : return 'Somalia'; break;
            case "193" : return 'South Africa'; break;
            case "194" : return 'South Georgia and the South Sandwich Islands'; break;
            case "195" : return 'Spain'; break;
            case "196" : return 'Sri Lanka'; break;
            case "197" : return 'St. Helena'; break;
            case "198" : return 'St. Pierre and Miquelon'; break;
            case "199" : return 'Sudan'; break;
            case "200" : return 'Suriname'; break;
            case "201" : return 'Svalbard and Jan Mayen Islands'; break;
            case "202" : return 'Swaziland'; break;
            case "203" : return 'Sweden'; break;
            case "204" : return 'Switzerland'; break;
            case "205" : return 'Syrian Arab Republic'; break;
            case "206" : return 'Taiwan'; break;
            case "207" : return 'Tajikistan'; break;
            case "208" : return 'Tanzania, United Republic of'; break;
            case "209" : return 'Thailand'; break;
            case "210" : return 'Togo'; break;
            case "211" : return 'Tokelau'; break;
            case "212" : return 'Tonga'; break;
            case "213" : return 'Trinidad and Tobago'; break;
            case "214" : return 'Tunisia'; break;
            case "215" : return 'Turkey'; break;
            case "216" : return 'Turkmenistan'; break;
            case "217" : return 'Turks and Caicos Islands'; break;
            case "218" : return 'Tuvalu'; break;
            case "219" : return 'Uganda'; break;
            case "220" : return 'Ukraine'; break;
            case "221" : return 'United Arab Emirates'; break;
            case "222" : return 'United Kingdom'; break;
            case "223" : return 'United States'; break;
            case "224" : return 'United States Minor Outlying Islands'; break;
            case "225" : return 'Uruguay'; break;
            case "226" : return 'Uzbekistan'; break;
            case "227" : return 'Vanuatu'; break;
            case "228" : return 'Vatican City State (Holy See)'; break;
            case "229" : return 'Venezuela'; break;
            case "230" : return 'Viet Nam'; break;
            case "231" : return 'Virgin Islands (British)'; break;
            case "232" : return 'Virgin Islands (U.S.)'; break;
            case "233" : return 'Wallis and Futuna Islands'; break;
            case "234" : return 'Western Sahara'; break;
            case "235" : return 'Yemen'; break;
            case "236" : return 'Yugoslavia'; break;
            case "237" : return 'Zaire'; break;
            case "238" : return 'Zambia'; break;
            case "239" : return 'Zimbabwe'; break;
		}
	}
	
	
	/**
	 * Validate an email address by regex and MX reccord
	 *
	 * @param string $Email An email address.
	 * @return void
	 * @author Scotty Delicious
	 */
	function ValidateEmail($Email)
	{
		// Original: ^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$
         if (filter_var(trim((string)$Email), FILTER_VALIDATE_EMAIL) === false) // FIX issue #1: validazione standard PHP al posto della regex
		{ 
			return $this->FormatMessage('The Email address you provided does not appear to be a properly formatted address.');
		}
		
		/*
		TOO MANY PROBLEMS WITH THIS VERIFICATION. I WILL COME BACK TO IT LATER.
		list($Username, $Domain) = split("@", $Email);
		if (getmxrr($Domain, $MXHost))
		{
			$ConnectAddress = $MXHost[0]; 
		}
		else
		{
			$ConnectAddress = $Domain;
		}
		
		if ($Connect = @fsockopen($ConnectAddress, 25, $errno, $errstr, 30))
		{
			if (ereg("^220", $Out = fgets($Connect, 1024)))
			{
				fputs($Connect, "HELO $HTTP_HOST\r\n"); 
				$Out = fgets($Connect, 1024);
				fputs($Connect, "MAIL FROM: <{$Email}>\r\n"); 
				$From = fgets($Connect, 1024);
				fputs($Connect, "RCPT TO: <{$Email}>\r\n"); 
				$To = fgets($Connect, 1024);
				fputs($Connect, "QUIT\r\n"); 
				fclose($Connect);

				if (!ereg("^250", $From) || !ereg("^250", $To))
				{
					return $this->FormatMessage('Server rejected address');
				}
			}
			else
			{
				return $this->FormatMessage('No response from server');
			}
		}
		else
		{
			return $this->FormatMessage('Cannot connect to email server '.$ConnectAddress);
		}*/
		// If we got to this point, the email has passed our tests
		// We could return a message that the email is valid, but 
		// as a true pirate, I will just keep moving on.
	}
	
	
	/**
	 * GeneratePassword
	 * Generate a random password of (int $length). [a-z][A-Z][2-9].
	 *
	 * @param int $length 
	 * @return void
	 * @author Raymond Irving
	 * @author Scotty Delicious
	 */
	function GeneratePassword($length = 10)
	{
        $allowable_characters = "abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
        $ps_len = strlen($allowable_characters);
        $pass = "";
        for($i = 0; $i < $length; $i++) {
            $pass .= $allowable_characters[random_int(0, $ps_len - 1)];
        }
        return $pass;
	}
	
	
	/**
	 * Fetch all rows in a data source recursively
	 *
	 * @param string $ds A data source.
	 * @return array $all An array of the data source
	 * @author Scotty Delicious
	 */
	function FetchAll($ds)
	{
		global $modx;
		
		$all = array();
		while ($all[] = $modx->db->getRow($ds)) {}
		foreach ($all as $key => $value)
		{
			if (empty($all[$key]))
			{
				unset($all[$key]);
			}
		}
		return $all;
	}
	
	/*********************************************************
	 * System Events
	 ********************************************************/

	function OnBeforeWebLogin()
	{
		global $modx;

		$parameters = array(
			'username'		=> $this->Username,
			'password'		=> $this->Password,
			'rememberme'	=> $this->PostValue('rememberme'),
			'stayloggedin'	=> $this->PostValue('stayloggedin')
			);
		$modx->invokeEvent("OnBeforeWebLogin", $parameters);
	}

	function OnWebLogin()
	{
		global $modx;
		$parameters = array('user' => $this->User, 'username' => $this->User['username']); // SMF connector fix http://modxcms.com/forums/index.php?topic=26565.0 c/o tazzydemon
		$modx->invokeEvent('OnWebLogin', $parameters);
	}

	function OnWebAuthentication()
	{
		global $modx;

		$parameters = array(
		    'internalKey'	=> $this->User['internalKey'],
		    'username'      => $this->Username,
		    'form_password'	=> $this->Password,
		    'db_password'	=> $this->User['password'],
		    'rememberme'    => $this->PostValue('rememberme'),
			'stayloggedin'	=> $this->PostValue('stayloggedin')
		);
		$modx->invokeEvent('OnWebAuthentication', $parameters);
	}

	function OnBeforeWebSaveUser($Attributes = array(), $ExtendedFields = array())
	{
		global $modx;

		$parameters = array(
			'Attributes'	=> $Attributes,
			'ExtendedFields'=> $ExtendedFields
			);
		$modx->invokeEvent('OnBeforeWebSaveUser', $parameters);
	}

	function OnWebSaveUser($mode = 'new', $user = array())
	{
		global $modx;

		$parameters = array(
			'mode'	=> $mode,
			'user'	=> $user,
			'username'=>(is_array($user) && isset($user['username'])) ? $user['username'] : ''  // (TS) SMF connector fix http://modxcms.com/forums/index.php?topic=26565.0
			);
		$modx->invokeEvent('OnWebSaveUser', $parameters);
	}
	
	function OnBeforeAddToGroup($groups = array())
	{
		global $modx;
		$parameters = array('groups' => $groups);
		$modx->invokeEvent('OnBeforeAddToGroup', $parameters);
	}
	
	function OnWebChangePassword($internalKey, $username, $newPassword)
	{
		global $modx; // pixelchutes 1:56 AM 9/19/2007
		$parameters = array(
			'internalKey'	=> $internalKey,
			'username'		=> $username,
			'password'		=> $newPassword
			);
        $modx->invokeEvent('OnWebChangePassword', $parameters);
	}
	
	function OnViewUserProfile($internalKey, $username, $viewerKey, $viewerName)
	{
		global $modx;
		$parameters = array(
			'internalKey'	=> $internalKey,
			'username'		=> $username,
			'viewerKey'		=> $viewerKey,
			'viewername'	=> $viewerName
			);
		$modx->invokeEvent('OnViewProfile', $parameters);
	}
	
	function OnWebDeleteUser($internalKey, $username)
	{
		global $modx;
		$parameters = array(
			'internalKey'	=> $internalKey,
			'username'		=> $username,
			'timestamp'		=> time()
			);
		$modx->invokeEvent('OnWebDeleteUser', $parameters);
	}
	
	function OnBeforeWebLogout()
	{
		global $modx;
		$parameters = array(
			'userid'		=> (isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : ''),
			'internalKey'	=> (isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : ''),
			'username'		=> (isset($_SESSION['webShortname']) ? $_SESSION['webShortname'] : '')
			);
		$modx->invokeEvent('OnBeforeWebLogout', $parameters);
	}
	
	function OnWebLogout($logoutparameters) // SMF connector fix http://modxcms.com/forums/index.php?topic=26565.0 c/o tazzydemon
	{
		global $modx;
		//$paramaters = array(
			//'userid'		=> (isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : ''),
			//'internalKey'	=> (isset($_SESSION['webInternalKey']) ? $_SESSION['webInternalKey'] : ''),
			//'username'		=> (isset($_SESSION['webShortname']) ? $_SESSION['webShortname'] : '')
		//	);
		$modx->invokeEvent('OnWebLogout', $logoutparameters);
	}
	
	
}
// end WebLoginPE Class

?>
