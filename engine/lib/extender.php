<?php
	/**
	 * Elgg Entity Extender.
	 * This file contains ways of extending an Elgg entity in custom ways.
	 * 
	 * @package Elgg
	 * @subpackage Core
	 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
	 * @author Marcus Povey
	 * @copyright Curverider Ltd 2008
	 * @link http://elgg.org/
	 */

	/**
	 * ElggExtender 
	 * 
	 * @author Marcus Povey
	 * @package Elgg
	 * @subpackage Core
	 */
	abstract class ElggExtender implements 
		Exportable,
		Loggable,	// Can events related to this object class be logged
		Iterator,	// Override foreach behaviour
		ArrayAccess // Override for array access
	{
		/**
		 * This contains the site's main properties (id, etc)
		 * @var array
		 */
		protected $attributes;
		
		/**
		 * Get an attribute
		 *
		 * @param string $name
		 * @return mixed
		 */
		protected function get($name) {
			if (isset($this->attributes[$name])) {
				
				// Sanitise value if necessary
				if ($name=='value')
				{
					switch ($this->attributes['value_type'])
					{
						case 'integer' :  return (int)$this->attributes['value'];
						//case 'tag' :
						//case 'file' :
						case 'text' : return ($this->attributes['value']);
							
						default : throw new InstallationException(sprintf(elgg_echo('InstallationException:TypeNotSupported'), $this->attributes['value_type']));
					}
				}
				
				return $this->attributes[$name];
			}
			return null;
		}
		
		/**
		 * Set an attribute
		 *
		 * @param string $name
		 * @param mixed $value
		 * @param string $value_type
		 * @return boolean
		 */
		protected function set($name, $value, $value_type = "") {

			$this->attributes[$name] = $value;
			$this->attributes['value_type'] = detect_extender_valuetype($value, $value_type);
			
			return true;
		}	
		
		/**
		 * Return the owner of this annotation.
		 *
		 * @return mixed
		 */
		public function getOwner() 
		{ 
			return get_user($this->owner_guid); 
		}
		
		/**
		 * Returns the entity this is attached to
		 *
		 * @return ElggEntity The enttiy
		 */
		public function getEntity() {
			return get_entity($this->entity_guid);
		}
		
		/**
		 * Save this data to the appropriate database table.
		 */
		abstract public function save();
		
		/**
		 * Delete this data.
		 */
		abstract public function delete();
		
		/**
		 * Determines whether or not the specified user can edit this
		 *
		 * @param int $user_guid The GUID of the user (defaults to currently logged in user)
		 * @return true|false
		 */
		public function canEdit($user_guid = 0) {
			return can_edit_extender($this->id,$this->type,$user_guid);
		}
		
		// EXPORTABLE INTERFACE ////////////////////////////////////////////////////////////
		
		/**
		 * Export this object
		 *
		 * @return array
		 */
		public function export()
		{
			$type = $this->attributes['type'];
			$uuid = guid_to_uuid($this->entity_guid). $type . "/{$this->id}/";
			
			$meta = new ODDMetadata($uuid, guid_to_uuid($this->entity_guid), $this->attributes['name'], $this->attributes['value'], $type, guid_to_uuid($this->owner_guid));
			$meta->setAttribute('published', date("r", $this->time_created));
			
			return $meta;
		}
		
		// SYSTEM LOG INTERFACE ////////////////////////////////////////////////////////////
		
		/**
		 * Return an identification for the object for storage in the system log. 
		 * This id must be an integer.
		 * 
		 * @return int 
		 */
		public function getSystemLogID() { return $this->id; }
		
		/**
		 * Return the class name of the object.
		 */
		public function getClassName() { return get_class($this); }
		
		/**
		 * Return the GUID of the owner of this object.
		 */
		public function getObjectOwnerGUID() { return $this->owner_guid; }
		
		
		// ITERATOR INTERFACE //////////////////////////////////////////////////////////////
		/*
		 * This lets an entity's attributes be displayed using foreach as a normal array.
		 * Example: http://www.sitepoint.com/print/php5-standard-library
		 */
		
		private $valid = FALSE; 
		
   		function rewind() 
   		{ 
   			$this->valid = (FALSE !== reset($this->attributes));  
   		}
   
   		function current() 
   		{ 
   			return current($this->attributes); 
   		}
		
   		function key() 
   		{ 
   			return key($this->attributes); 
   		}
		
   		function next() 
   		{
   			$this->valid = (FALSE !== next($this->attributes));  
   		}
   		
   		function valid() 
   		{ 
   			return $this->valid;  
   		}
	
   		// ARRAY ACCESS INTERFACE //////////////////////////////////////////////////////////
		/*
		 * This lets an entity's attributes be accessed like an associative array.
		 * Example: http://www.sitepoint.com/print/php5-standard-library
		 */

		function offsetSet($key, $value)
		{
   			if ( array_key_exists($key, $this->attributes) ) {
     			$this->attributes[$key] = $value;
   			}
 		} 
 		
 		function offsetGet($key) 
 		{
   			if ( array_key_exists($key, $this->attributes) ) {
     			return $this->attributes[$key];
   			}
 		} 
 		
 		function offsetUnset($key) 
 		{
   			if ( array_key_exists($key, $this->attributes) ) {
     			$this->attributes[$key] = ""; // Full unsetting is dangerious for our objects
   			}
 		} 
 		
 		function offsetExists($offset) 
 		{
   			return array_key_exists($offset, $this->attributes);
 		} 
	}
	
	/**
	 * Detect the value_type for a given value.
	 * Currently this is very crude.
	 * 
	 * TODO: Make better!
	 *
	 * @param mixed $value
	 * @param string $value_type If specified, overrides the detection.
	 * @return string
	 */
	function detect_extender_valuetype($value, $value_type = "")
	{
		if ($value_type!="")
			return $value_type;
			
		// This is crude
		if (is_int($value)) return 'integer';
		if (is_numeric($value)) return 'integer';
		
		return 'text';
	}
	
	/**
	 * Utility function used by import_extender_plugin_hook() to process an ODDMetaData and add it to an entity.
	 * This function does not hit ->save() on the entity (this lets you construct in memory)
	 *
	 * @param ElggEntity The entity to add the data to.
	 * @param ODDMetaData $element The OpenDD element
	 * @return bool
	 */
	function oddmetadata_to_elggextender(ElggEntity $entity, ODDMetaData $element)
	{
		// Get the type of extender (metadata, type, attribute etc)
		$type = $element->getAttribute('type');
		$attr_name = $element->getAttribute('name');
		$attr_val = $element->getBody();

		switch ($type)
		{
			case 'volatile' : break; // Ignore volatile items
			case 'annotation' : 
				$entity->annotate($attr_name, $attr_val);
			break;
			case 'metadata' :
				$entity->setMetaData($attr_name, $attr_val, "", true);
			break;
			default : // Anything else assume attribute
				$entity->set($attr_name, $attr_val);			
		}
		
		// Set time if appropriate
		$attr_time = $element->getAttribute('published');
		if ($attr_time)
			$entity->set('time_updated', $attr_time);
			
		return true;
	}
	
	/**
	 *  Handler called by trigger_plugin_hook on the "import" event.
	 */
	function import_extender_plugin_hook($hook, $entity_type, $returnvalue, $params)
	{
		$element = $params['element'];
		
		$tmp = NULL;
		
		if ($element instanceof ODDMetaData)
		{
			// Recall entity
			$entity_uuid = $element->getAttribute('entity_uuid');
			$entity = get_entity_from_uuid($entity_uuid);
			if (!$entity)
				throw new ImportException(sprintf(elgg_echo('ImportException:GUIDNotFound'), $entity_uuid));
			
			oddmetadata_to_elggextender($entity, $element);
	
			// Save
			if (!$entity->save())
				throw new ImportException(sprintf(elgg_echo('ImportException:ProblemUpdatingMeta'), $attr_name, $entity_uuid));
			
			return true;
		}
	}
	
	/**
	 * Determines whether or not the specified user can edit the specified piece of extender
	 *
	 * @param int $extender_id The ID of the piece of extender
	 * @param string $type 'metadata' or 'annotation'
	 * @param int $user_guid The GUID of the user
	 * @return true|false
	 */
	function can_edit_extender($extender_id, $type, $user_guid = 0) {
		
		if (!isloggedin())
			return false;
		
		if ($user_guid == 0) {
			if (isset($_SESSION['user'])) {
				$user = $_SESSION['user'];
			} else {
				$user = null;
			}
		} else {
			$user = get_entity($user_guid);
		}

		$functionname = "get_{$type}";
		if (is_callable($functionname)) {
			$extender = $functionname($extender_id);
		} else return false;
		
		if (!is_a($extender,"ElggExtender")) return false;
		
		// If the owner is the specified user, great! They can edit.
		if ($extender->getOwner() == $user->getGUID()) return true;
		
		// If the user can edit the entity this is attached to, great! They can edit.
		if (can_edit_entity($extender->entity_guid,$user->getGUID())) return true;
		
		// Trigger plugin hooks
		return trigger_plugin_hook('permissions_check',$type,array('entity' => $entity, 'user' => $user),false);
		
	}
	
	/** Register the hook */
	register_plugin_hook("import", "all", "import_extender_plugin_hook", 2);
	
?>