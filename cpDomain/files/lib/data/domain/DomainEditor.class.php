<?php
// wcf imports
if (!defined('NO_IMPORTS'))
{
	require_once (CP_DIR . 'lib/data/domain/Domain.class.php');
}

/**
 * DomainEditor creates, edits or deletes domains and subdomains.
 *
 * @author		Tobias Friebel
 * @copyright	2010 Tobias Friebel
 * @license		GNU General Public License <http://opensource.org/licenses/gpl-2.0.php>
 * @package		com.toby.cp.domains
 * @subpackage	data.domain
 * @category 	Control Panel
 * @id			$Id$
 */
class DomainEditor extends Domain
{
	/**
	 * Creates a new domain with all required and filled out additional fields.
	 *
	 * @param 	string 		$domainame
	 * @param	string		$documentroot
	 * @param	int			$userID
	 * @param	int 		$adminID
	 * @param	int			$parentDomainID (if not 0 this is a subdomain!)
	 * @param	array		$domainOptions
	 * @param	array		$additionalFields
	 *
	 * @return 	DomainEditor
	 */
	public static function create($domainame, $userID, $adminID, $parentDomainID = 0, $domainOptions = array(), $additionalFields = array())
	{
		// insert main data
		$domainID = self :: insert($domainame, $userID, $adminID, $parentDomainID, $additionalFields);

		// insert domain options
		self :: insertDomainOptions($domainID, $domainOptions);

		$domain = new DomainEditor($domainID);

		return $domain;
	}

	/**
	 * Inserts the main domain data into the domain table.
	 *
	 * @param 	string 		$domainame
	 * @param 	string 		$documentroot
	 * @param	int			$userID
	 * @param	int 		$adminID
	 * @param	int			$parentDomainID (if not 0 this is a subdomain!)
	 * @param	array		$additionalFields
	 *
	 * @return 	integer		new domainID
	 */
	public static function insert($domainname, $userID, $adminID, $parentDomainID, $additionalFields = array())
	{
		$additionalColumnNames = $additionalColumnValues = '';

		if (!isset($additionalFields['addDate']))
			$additionalFields['addDate'] = TIME_NOW;

		foreach ($additionalFields as $key => $value)
		{
			$additionalColumnNames .= ', ' . $key;
			$additionalColumnValues .= ', ' . ((is_int($value)) ? $value : "'" . escapeString($value) . "'");
		}

		$sql = "INSERT INTO	cp" . CP_N . "_domain
						(domainname, userID, adminID, parentDomainID
						" . $additionalColumnNames . ")
				VALUES	('" . escapeString($domainname) . "',
						" . intval($userID) . ",
						" . intval($adminID) . ",
						" . intval($parentDomainID) . "
						" . $additionalColumnValues . ")";
		WCF :: getDB()->sendQuery($sql);
		$domainID = WCF :: getDB()->getInsertID();

		if ($parentDomainID != 0)
		{
			require_once (CP_DIR . 'lib/data/user/CPUser.class.php');
			$user = new CPUser($userID);
			$user->getEditor()->updateOptions(array('subdomainsUsed' => ++$user->subdomainsUsed));
		}

		return $domainID;
	}

	/**
	 * Inserts the additional domain data into the domain table.
	 *
	 * @param 	integer		$domainID
	 * @param 	array 		$domainOptions
	 * @param	boolean		$update
	 */
	protected static function insertDomainOptions($domainID, $domainOptions = array(), $update = false)
	{
		// get default values from options.
		$defaultValues = array ();
		if (!$update)
		{
			$sql = "SELECT	optionID, defaultValue
					FROM	cp" . CP_N . "_domain_option";
			$result = WCF :: getDB()->sendQuery($sql);

			while ($row = WCF :: getDB()->fetchArray($result))
			{
				if ($row['defaultValue'])
				{
					$defaultValues[$row['optionID']] = $row['defaultValue'];
				}
			}
		}

		// build the sql strings.
		$columnNames = $columnValues = $updateColumns = '';
		foreach ($domainOptions as $option)
		{
			$columnNames .= ', domainOption' . $option['optionID'];
			$columnValues .= ", '" . escapeString($option['optionValue']) . "'";

			if (!empty($updateColumns))
				$updateColumns .= ',';
			$updateColumns .= 'domainOption' . $option['optionID'] . ' = VALUES(domainOption' . $option['optionID'] . ')';

			// the value of this option was send via "activeOptions".
			unset($defaultValues[$option['optionID']]);
		}

		// add default values from inactive options.
		foreach ($defaultValues as $optionID => $optionValue)
		{
			$columnNames .= ', domainOption' . $optionID;
			$columnValues .= ", '" . escapeString($optionValue) . "'";

			if (!empty($updateColumns))
				$updateColumns .= ',';
			$updateColumns .= 'domainOption' . $optionID . ' = VALUES(domainOption' . $optionID . ')';
		}

		// insert option values to domain record.
		if (!$update || !empty($updateColumns))
		{
			$sql = "INSERT INTO			cp" . CP_N . "_domain_option_value
									(domainID" . $columnNames . ")
					VALUES 			(" . $domainID . $columnValues . ")
				" . (!empty($updateColumns) ? "ON DUPLICATE KEY UPDATE " . $updateColumns : "");
			WCF :: getDB()->sendQuery($sql);
		}
	}

	/**
	 * Updates this domain.
	 *
	 * @param	string		$domainname
	 * @param	string		$documentroot
	 * @param	int			$userID
	 * @param	int			$adminID
	 * @param	array		$dynamicOptions
	 * @param	array 		$additionalFields
	 * @param 	array		$visibleLanguages
	 */
	public function update($domainname, $userID, $adminID, $parentDomainID, $dynamicOptions = null, $additionalFields = array())
	{
		$this->updateDomain($domainname, $userID, $adminID, $parentDomainID, $additionalFields);

		if ($dynamicOptions !== null)
			self :: insertDomainOptions($this->domainID, $dynamicOptions, true);
	}

	/**
	 * Updates additional domain fields.
	 *
	 * @param	array 	$additionalFields
	 */
	public function updateFields($additionalFields)
	{
		$this->updateDomain('', '', 0, 0, $additionalFields);
	}

	/**
	 * Updates the given domain options.
	 *
	 * @param	array	$options
	 */
	public function updateOptions($options)
	{
		// get user option cache if necessary
		if (self :: $domainOptions === null)
		{
			self :: getDomainOptionCache();
		}

		$dynamicOptions = array ();
		foreach ($options as $name => $value)
		{
			if (isset(self :: $domainOptions[$name]))
			{
				$option = self :: $domainOptions[$name];
				$option['optionValue'] = $value;
				$dynamicOptions[] = $option;
			}
		}

		$this->update('', '', 0, 0, $dynamicOptions);
	}

	/**
	 * Updates the static data of this domain.
	 *
 	 * @param 	string 		$domainame
	 * @param 	string 		$documentroot
	 * @param	int			$userID
	 * @param	int 		$adminID
	 * @param	array		$additionalFields
	 */
	protected function updateDomain($domainname = '', $userID = 0, $adminID = 0, $parentDomainID = 0, $additionalFields = array())
	{
		$updateSQL = '';
		if (!empty($domainname))
		{
			$updateSQL = "domainname = '" . escapeString($domainname) . "'";
			$this->domainname = $domainname;
		}

		if (!empty($userID))
		{
			if (!empty($updateSQL))
				$updateSQL .= ',';
			$updateSQL .= "userID = " . intval($userID);
			$this->userID = $userID;
		}

		if (!empty($adminID))
		{
			if (!empty($updateSQL))
				$updateSQL .= ',';
			$updateSQL .= "adminID = " . intval($adminID);
			$this->adminID = $adminID;
		}

		if (!empty($parentDomainID))
		{
			if (!empty($updateSQL))
				$updateSQL .= ',';
			$updateSQL .= "parentDomainID = " . intval($parentDomainID);
			$this->parentDomainID = $parentDomainID;
		}

		foreach ($additionalFields as $key => $value)
		{
			if (!empty($updateSQL))
				$updateSQL .= ',';
			$updateSQL .= $key . '=' . ((is_int($value)) ? $value : "'" . escapeString($value) . "'");
		}

		if (!empty($updateSQL))
		{
			// save user
			$sql = "UPDATE	cp" . CP_N . "_domain
					SET	" . $updateSQL . "
					WHERE 	domainID = " . $this->domainID;
			WCF :: getDB()->sendQuery($sql);
		}
	}

	/**
	 * adds default subdomain
	 * TODO: implement!
	 *
	 * @param	int		$userID
	 */
	public static function addDefaultSubDomain($userID)
	{

	}

	/**
	 * removes default subdomain
	 * TODO: implement!
	 *
	 * @param	int		$userID
	 */
	public static function removeDefaultSubDomain($userID)
	{

	}

	/**
	 * Get an array with all domainIDs for given userID
	 *
	 * @param int $userID
	 *
	 * @return array
	 */
	protected static function getIDsForUser($userID)
	{
		$sql = "SELECT domainID
				FROM cp" . CP_N . "_domain
				WHERE userID = " . intval($userID);

		$result = WCF :: getDB()->sendQuery($sql);

		$domainIDsStr = array();
		while ($row = WCF :: getDB()->fetchArray($result))
		{
			$domainIDsStr[] = $row['domainID'];
		}

		return $domainIDsStr;
	}

	/**
	 * Deletes all domains for given user
	 *
	 * @param	int		$userID
	 */
	public static function deleteAll($userID)
	{
		$domainIDsStr = implode(',', self :: getIDsForUser($userID));

		// delete options for this domain
		$sql = "DELETE 	FROM cp" . CP_N . "_domain_option_value
				WHERE 	domainID IN (" . $domainIDsStr . ")";
		WCF :: getDB()->sendQuery($sql);

		// delete domain from domain table
		$sql = "DELETE 	FROM cp" . CP_N . "_domain
				WHERE 	domainID IN (" . $domainIDsStr . ")";
		WCF :: getDB()->sendQuery($sql);

		require_once (CP_DIR . 'lib/data/user/CPUser.class.php');
		$user = new CPUser($userID);
		$user->getEditor()->updateOptions(array('subdomainsUsed' => 0));
	}

	/**
	 * Deletes this domain
	 */
	public function delete()
	{
		// delete options for this domain
		$sql = "DELETE 	FROM cp" . CP_N . "_domain_option_value
				WHERE 	domainID = " . $this->domainID;
		WCF :: getDB()->sendQuery($sql);

		// delete domain from domain table
		$sql = "DELETE 	FROM cp" . CP_N . "_domain
				WHERE 	domainID = " . $this->domainID;
		WCF :: getDB()->sendQuery($sql);

		if ($this->parentDomainID != 0)
		{
			$user = new CPUser($this->userID);
			$user->getEditor()->updateOptions(array('subdomainsUsed' => --$user->subdomainsUsed));
		}
	}

	/**
	 * disable this domain
	 */
	public function disable()
	{
		$sql = "UPDATE 	cp" . CP_N . "_domain
				SET		deactivated = 1
				WHERE 	domainID = " . $this->domainID;
		WCF :: getDB()->sendQuery($sql);
	}

	/**
	 * enable this domain
	 */
	public function enable()
	{
		$sql = "UPDATE 	cp" . CP_N . "_domain
				SET		deactivated = 0
				WHERE 	domainID = " . $this->domainID;
		WCF :: getDB()->sendQuery($sql);
	}

	/**
	 * Disable domains.
	 * Returns the number of disabled domains.
	 *
	 * @param	array		$domainIDs
	 * @return	integer
	 */
	public static function disableAll($userID)
	{
		$domainIDsStr = implode(',', self :: getIDsForUser($userID));

		$sql = "UPDATE 	cp" . CP_N . "_domain
				SET		deactivated = 1
				WHERE 	domainID IN (" . $domainIDsStr . ")";
		WCF :: getDB()->sendQuery($sql);

		return WCF :: getDB()->getAffectedRows();
	}

	/**
	 * enable domains for user
	 * Returns the number of enabled domains.
	 *
	 * @param	int		$userID
	 * @return	integer
	 */
	public static function enableAll($userID)
	{
		$domainIDsStr = implode(',', self :: getIDsForUser($userID));

		$sql = "UPDATE 	cp" . CP_N . "_domain
				SET		deactivated = 0
				WHERE 	domainID IN (" . $domainIDsStr . ")";
		WCF :: getDB()->sendQuery($sql);

		return WCF :: getDB()->getAffectedRows();
	}

	/**
	 * Unmarks all marked domains.
	 */
	public static function unmarkAll()
	{
		WCF :: getSession()->unregister('markedDomains');
	}

	/**
	 * Returns true, if this domain is marked.
	 *
	 * @return 	boolean
	 */
	public function isMarked()
	{
		$sessionVars = WCF :: getSession()->getVars();
		if (isset($sessionVars['markedDomains']))
		{
			if (in_array($this->domainID, $sessionVars['markedDomains']))
				return 1;
		}

		return 0;
	}
}
?>