<?php

define('__MENTION_USERS__', ossn_route()->com . 'MentionUsers/');

function com_mention_init() {
     //css
     ossn_extend_view('css/ossn.default', 'css/mentionusers');

     ossn_register_callback('wall', 'post:created', 'com_wall_created');
     ossn_register_callback('comment', 'created', 'com_comment_created');
     ossn_register_callback('annotation', 'delete', 'com_removeAnnotationMentionNotification', 199);
     ossn_register_callback('post', 'delete', 'com_removePostMentionNotification');
     ossn_register_callback('user', 'delete', 'com_removeUserMentionNotification');

     //hook for wall posts
     ossn_add_hook("notification:add", "mention:post:created", "com_mention_notifier");
     ossn_add_hook("notification:view", "mention:post:created", "com_mention_notifier_view_notification");
     //hook for comments
     ossn_add_hook("notification:add", "mention:comment:created", "com_mention_notifier");
     ossn_add_hook("notification:view", "mention:comment:created", "com_mention_notifier_view_notification");
}

function com_wall_created($callback, $type, $params) {
     $new_wall_post_id = $params['object_guid'];
     $wall_post = ossn_get_object($new_wall_post_id);
     $description = json_decode($wall_post->description, true);
     $post = $description['post'];
     com_notificationHandler($callback, $type, $params, $post);
}

function com_comment_created($callback, $type, $params) {
     $comment = $params['value'];
     com_notificationHandler($callback, $type, $params, $comment);
}

function com_mention_notifier($hook, $type, $return, $params) {
     $return = $params;
     $return["owner_guid"] = $params["notification_owner"];
     return $return;
}

function com_notificationHandler($callback, $type, $params, $message) {
     // initialize notifications and the array to house people we have already sent a notification to. This is to help us not send duplicates
     $notifiedUsersArr = array();
     $notifications = new OssnNotifications;
     $hasDisplayUsername = com_is_active('DisplayUsername');
     while (($start = strpos($message, '@')) !== false) { // find substring starting after '@'
          $substr = substr($message, $start + 1);
          if ($substr) {
               if (preg_match('/(\r\n|\r|\n)/u', $substr)) {
                    $substr = preg_replace('/(\r\n|\r|\n)/u', ' ', $substr);
               }
               // error_log('substr = ' . $substr);
               $substr_words = explode(" ", $substr); // convert to array of separate words
               // error_log('substr_words: ' . ossn_dump($substr_words));
               if(count($substr_words) >= 1 && $substr_words[0] !== '@') {
                    // build your own query -> see OssnDatabase->select() ...
                    $db = new OssnDatabase;
                    $found = false;
                    while(count($substr_words) > 0 && $found == false) {
                         $substr_phrase = implode(' ', $substr_words);
                         if (preg_match('/[!@#$%^&*(),.?":{}|<>]/u', $substr_phrase)) {
                              $count_periods = substr_count($substr_phrase, '.');
                              $last_char = substr($substr_phrase, strlen($substr_phrase) - 1, 1);
                              if (($count_periods == 1 && $last_char == '.') || $count_periods == 0) {
                                   $substr_phrase = preg_replace('/\s\s+/u', ' ', preg_replace('/[!@#$%^&*(),.?":{}|<>]/u', '', $substr_phrase));
                              }
                              elseif ($last_char == '.') {
                                   $substr_phrase = preg_replace('/\s\s+/u', ' ', substr($substr_phrase, 1, strlen($substr_phrase) - 1)); 
                              }
                         }
                         // error_log('substr_phrase = ' . $substr_phrase);
                         if($hasDisplayUsername == true) {
                              $query['params'] = array(
                                   'guid',
                                   'username AS name'
                              );
                              $query['from'] = 'ossn_users';
                              $query['wheres'] = array(
                                   "username = '{$substr_words[0]}'",
                              );
                         }
                         else {
                              $query['params'] = array(
                                   'guid',
                                   'first_name',
                                   'last_name',
                                   'CONCAT(first_name, \' \', last_name) AS name'
                              );
                              $query['from'] = 'ossn_users';
                              $query['wheres'] = array(
                                   "CONCAT(first_name, ' ', last_name) = '" . trim($substr_phrase) . "'",
                              );
                         }
                         // execute the query
                         $members = $db->select($query, true);
                         if ($members) {
                              $found = true;
                              break;
                         }
                         else {
                              array_pop($substr_words);
                         }
                    }
                    if($members) {
                         // we got a match on $members
                         // maybe more than one is matching - so let's loop
                         foreach($members as $member) {
                              if(in_array($member, $notifiedUsersArr) == false && ($hasDisplayUsername == true || ($hasDisplayUsername == false && mb_strpos($substr_phrase, $member->name) !== false))) {
                                   // error_log('Notify!');
                                   // found one!
                                   // if we have a poster guid then this is a wall post else its a comment so the payload changes
                                   if (strlen($params['poster_guid']) > 0) {
                                        $notifications->add('mention:post:created', $params['poster_guid'], $params['object_guid'], $params['object_guid'], $member->guid);
                                   }
                                   else {
                                        $notifications->add('mention:comment:created', $params['owner_guid'], $params['subject_guid'], $params['id'], $member->guid);
                                   }
                                   array_push($notifiedUsersArr, $member);
                                   break;
                              }
                         }
                    }
               }
          }
          $message = $substr; // try to find another mention in remaining part of original message string
     }
}

function com_mention_notifier_view_notification($hook, $type, $return, $params) {
     $notif          = $params;
     $baseurl        = ossn_site_url();
     $user           = ossn_user_by_guid($notif->poster_guid);
     if (com_is_active('DisplayUsername')) {
          $name = $user->username;
     }
     else {
          $name = $user->fullname;
     }
     $user->fullname = "<strong>{$name}</strong>";
     $iconURL        = $user->iconURL()->small;

     $img = "<div class='notification-image'><img src='{$iconURL}' /></div>";
     $url = ossn_site_url("post/view/{$notif->subject_guid}");

     if (preg_match('/post/i', $notif->type)) {
          $url  = ossn_site_url("post/view/{$notif->subject_guid}");
     }
     $type = "<div class='mention-icon'></div>";
     if ($notif->viewed !== NULL) {
          $viewed = '';
     } elseif ($notif->viewed == NULL) {
          $viewed = 'class="ossn-notification-unviewed"';
     }
     $notification_read = "{$baseurl}notification/read/{$notif->guid}?notification=" . urlencode($url);
     return "<a href='{$notification_read}'>
	   <li {$viewed}> {$img} 
	   <div class='notfi-meta'> {$type}
	   <div class='data'>" . ossn_print("{$notif->type}", array(
          $user->fullname
     )) . '</div>
	   </div></li></a>';
}

function com_removeAnnotationMentionNotification($type, $params, $data) {
     // error_log('ANNOTATION ' . ossn_dump($data));
     $notifications = new OssnNotifications;
     $notifications->deleteNotification(array(
         'type' => 'mention:comment:created',
         'item_guid' => $data['annotation']
     ));
 }
 
 function com_removePostMentionNotification($type, $params, $data) {
     // error_log('POST ' . ossn_dump($data));
     $notifications = new OssnNotifications;
     $notifications->deleteNotification(array(
         'type' =>'mention:post:created', 
         'subject_guid' => $data
     ));
 }
 
 function com_removeUserMentionNotification($type, $params, $data) {
     // error_log('USER ' . ossn_dump($data));
     $notifications = new OssnNotifications;
     $user = $data['entity'];
     $notifications->deleteNotification(array(
         'poster_guid' => $user->guid,
         'type' => 'mention:post:created',
     ));
     $notifications->deleteNotification(array(
         'poster_guid' => $user->guid,
         'type' => 'mention:comment:created',
     ));
 }

ossn_register_callback('ossn', 'init', 'com_mention_init');
