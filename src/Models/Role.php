<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Models;

/**
 * Represents a role.
 *
 * @property \CharlotteDunois\Yasmin\Models\Guild        $guild               The guild the role belongs to.
 * @property string                                      $id                  The role ID.
 * @property string                                      $name                The role name.
 * @property int                                         $createdTimestamp    When the role was created.
 * @property int                                         $color               The color of the role.
 * @property bool                                        $hoist               Whether the role gets displayed separately in the member list.
 * @property int                                         $position            The position of the role in the API.
 * @property \CharlotteDunois\Yasmin\Models\Permissions  $permissions         The permissions of the role.
 * @property bool                                        $managed             Whether the role is managed by an integration.
 * @property bool                                        $mentionable         Whether the role is mentionable.
 *
 * @property int                                         $calculatedPosition  The role position in the role manager.
 * @property \DateTime                                   $createdAt           The DateTime instance of createdTimestamp.
 * @property bool                                        $editable            Whether the role can be edited by the client user.
 * @property string                                      $hexColor            Returns the hex color of the role color.
 * @property \CharlotteDunois\Yasmin\Utils\Collection    $members             A collection of all (cached) guild members which have the role.
 */
class Role extends ClientBase {
    protected $guild;
    
    protected $id;
    protected $name;
    protected $color;
    protected $hoist;
    protected $position;
    protected $permissions;
    protected $managed;
    protected $mentionable;
    
    protected $createdTimestamp;
    
    /**
     * @internal
     */
    function __construct(\CharlotteDunois\Yasmin\Client $client, \CharlotteDunois\Yasmin\Models\Guild $guild, array $role) {
        parent::__construct($client);
        $this->guild = $guild;
        
        $this->id = $role['id'];
        $this->createdTimestamp = (int) \CharlotteDunois\Yasmin\Utils\Snowflake::deconstruct($this->id)->timestamp;
        
        $this->_patch($role);
    }
    
    /**
     * @inheritDoc
     *
     * @throws \Exception
     * @internal
     */
    function __get($name) {
        if(\property_exists($this, $name)) {
            return $this->$name;
        }
        
        switch($name) {
            case 'calculatedPosition':
                $sorted = $this->guild->roles->sortByDesc(function ($role) {
                    return $role->position;
                });
                
                return $sorted->indexOf($this);
            break;
            case 'createdAt':
                return \CharlotteDunois\Yasmin\Utils\DataHelpers::makeDateTime($this->createdTimestamp);
            break;
            case 'editable':
                if($this->managed) {
                    return false;
                }
                
                $member = $this->guild->me;
                if(!$member->permissions->has(\CharlotteDunois\Yasmin\Constants::PERMISSIONS['MANAGE_ROLES'])) {
                    return false;
                }
                
                return ($member->highestRole->comparePositionTo($this) > 0);
            break;
            case 'hexColor':
                return '#'.\dechex($this->color);
            break;
            case 'members':
                if($this->id === $this->guild->id) {
                    return $this->guild->members->copy();
                }
                
                return $this->guild->members->filter(function ($member) {
                    return $member->roles->has($this->id);
                });
            break;
        }
        
        return parent::__get($name);
    }
    
    /**
     * Compares the position from the role to the given role.
     * @param \CharlotteDunois\Yasmin\Models\Role  $role
     * @return int
     */
    function comparePositionTo(\CharlotteDunois\Yasmin\Models\Role $role) {
        if($this->position === $role->position) {
            return $role->id <=> $this->id;
        }
        
        return $this->position <=> $role->position;
    }
    
    /**
     * Edits the role with the given options. Resolves with $this.
     *
     * Options are as following (only one is required):
     *
     * <pre>
     * array(
     *   'name' => string,
     *   'color' => int|string,
     *   'hoist' => bool,
     *   'position' => int,
     *   'permissions' => int|\CharlotteDunois\Yasmin\Models\Permissions,
     *   'mentionable' => bool
     * )
     * </pre>
     *
     * @param array  $options
     * @param string $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveColor()
     */
    function edit(array $options, string $reason = '') {
        if(empty($options)) {
            throw new \InvalidArgumentException('Unable to edit role with zero information');
        }
        
        $data = array();
        
        if(isset($options['name'])) {
            $data['name'] = $options['name'];
        }
        
        if(isset($options['color'])) {
            $data['color'] = \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveColor($options['color']);
        }
        
        if(isset($options['hoist'])) {
            $data['hoist'] = (bool) $options['hoist'];
        }
        
        if(isset($options['position'])) {
            $data['position'] = (int) $options['position'];
        }
        
        if(isset($options['permissions'])) {
            $data['permissions'] = $options['permissions'];
        }
        
        if(isset($options['mentionable'])) {
            $data['mentionable'] = (bool) $options['mentionable'];
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($data, $reason) {
            $this->client->apimanager()->endpoints->guild->modifyGuildRole($this->guild->id, $this->id, $data, $reason)->then(function () use ($resolve) {
                $resolve($this);
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Deletes the role.
     * @param string  $reason
     * @return \React\Promise\Promise
     */
    function delete(string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($reason) {
            $this->client->apimanager()->endpoints->guild->deleteGuildRole($this->guild->id, $this->id, $reason)->then(function () use ($resolve) {
                $resolve();
            }, $reject)->done(null, array($this->client, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Set the color of the role. Resolves with $this.
     * @param int|string  $color
     * @param string      $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveColor()
     */
    function setColor($color, string $reason = '') {
        return $this->edit(array('color' => $color), $reason);
    }
    
    /**
     * Set whether or not the role should be hoisted. Resolves with $this.
     * @param bool    $hoist
     * @param string  $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     */
    function setHoist(bool $hoist, string $reason = '') {
        return $this->edit(array('hoist' => $hoist), $reason);
    }
    
    /**
     * Set whether the role is mentionable. Resolves with $this.
     * @param bool    $mentionable
     * @param string  $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     */
    function setMentionable(bool $mentionable, string $reason = '') {
        return $this->edit(array('mentionable' => $mentionable), $reason);
    }
    
    /**
     * Set a new name for the role. Resolves with $this.
     * @param string  $name
     * @param string  $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     */
    function setName(string $name, string $reason = '') {
        return $this->edit(array('name' => $name), $reason);
    }
    
    /**
     * Set the permissions of the role. Resolves with $this.
     * @param int|\CharlotteDunois\Yasmin\Models\Permissions  $permissions
     * @param string                                          $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     */
    function setPermissions($permissions, string $reason = '') {
        return $this->edit(array('permissions' => $permissions), $reason);
    }
    
    /**
     * Set the position of the role. Resolves with $this.
     * @param int     $position
     * @param string  $reason
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     */
    function setPosition(int $position, string $reason = '') {
        return $this->edit(array('position' => $position), $reason);
    }
    
    /**
     * Automatically converts to a mention.
     */
    function __toString() {
        return '<@&'.$this->id.'>';
    }
    
    /**
     * @internal
     */
    function _patch(array $role) {
        $this->name = $role['name'];
        $this->color = $role['color'];
        $this->hoist = $role['hoist'];
        $this->position = $role['position'];
        $this->permissions = new \CharlotteDunois\Yasmin\Models\Permissions($role['permissions']);
        $this->managed = $role['managed'];
        $this->mentionable = $role['mentionable'];
    }
}
