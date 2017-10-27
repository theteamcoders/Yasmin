<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\WebSocket\Events;

/**
 * WS Event
 * @see https://discordapp.com/developers/docs/topics/gateway#guild-delete
 * @access private
 */
class GuildDelete {
    protected $client;
    
    function __construct(\CharlotteDunois\Yasmin\Client $client) {
        $this->client = $client;
    }
    
    function handle(array $data) {
        $guild = $this->client->guilds->get($data['id']);
        if($guild) {
            if($data['unavailable'] === true) {
                $guild->_patch(array('unavailable' => true));
            } else {
                $this->client->guilds->delete($guild->id);
                $this->client->emit('guildDelete', $guild);
            }
        }
    }
}