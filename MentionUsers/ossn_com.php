<?php

define('__MENTION_USERS__', ossn_route()->com . 'MentionUsers/');

function com_init() {
     ossn_register_callback('wall', 'post:created', 'com_wall_created');
     ossn_register_callback('comment', 'created', 'com_comment_created');
     //hook for wall posts
     ossn_add_hook("notification:add", "post:created", "com_mention_notifier");
     ossn_add_hook("notification:view", "post:created", "com_mention_notifier_view_notification");
     //hook for comments
     ossn_add_hook("notification:add", "created", "com_mention_notifier");
     ossn_add_hook("notification:view", "created", "com_mention_notifier_view_notification");
}

function com_wall_created($callback, $type, $params) {
     $new_wall_post_id = $params['object_guid'];
     $wall_post = ossn_get_object($new_wall_post_id);
     $post = $wall_post->description;
     com_notificationHandler($callback, $type, $params, strval($post));
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
     $messageArr = str_split($message, 1);
     $notifications = new OssnNotifications;
     $user = new OssnUser;
     $users = $user->getSiteUsers(['page_limit' => false]);
     $notifyUsersArr = array();

     // load up the array of users to notify
     foreach ($messageArr as $messagekey => $messagevalue) {
          if ($messagevalue == "@") {
               foreach ($users as $key => $value) {
                    // check to see if the component from https://www.opensource-socialnetwork.org/component/view/3065/display-username is enabled. 
                    // if it is then we search the mentioned user by user name
                    if (com_is_active('DisplayUsername')) {
                         $name = $value->username;
                         $len = strlen($name);
                         // we want to exact match the user names since they can be the characters but different casing whereas names the casing shouldnt matter
                         if (substr($message, $messagekey + 1, $len) == $name) {
                              // only add the user to the array if they are not in it already
                              if (!in_array($value, $notifyUsersArr)) {
                                   array_push($notifyUsersArr, $value);
                              }
                         }
                    }
                    // else we dont have the component enabled so the mention should be by full name
                    else {
                         $name = $value->fullname;
                         $len = strlen($name);
                         // we can lowercase the names because they dont matter in regards to casing
                         if (strtolower(substr($message, $messagekey + 1, $len)) == strtolower($name)) {
                              if (!in_array($value, $notifyUsersArr)) {
                                   // only add the user to the array if they are not in it already
                                   array_push($notifyUsersArr, $value);
                              }                         
                         }
                    }
               }
          }
     }

     // notify each user
     foreach ($notifyUsersArr as $key => $value) {
          // if we have a poster guid then this is a wall post else its a comment so the payload changes
          if (strlen($params['poster_guid']) > 0) {
               $notifications->add($type, $params['poster_guid'], $params['object_guid'], $params['object_guid'], $value->guid);
          }
          else {
               $notifications->add($type, $params['owner_guid'], $params['subject_guid'], $params['id'], $value->guid);
          }
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
          $type = 'comment';
          $url  = ossn_site_url("post/view/{$notif->subject_guid}");
     }
     $type = "<div class='ossn-notification-icon-{$type}'></div>";
     if ($notif->viewed !== NULL) {
          $viewed = '';
     } elseif ($notif->viewed == NULL) {
          $viewed = 'class="ossn-notification-unviewed"';
     }
     $notification_read = "{$baseurl}notification/read/{$notif->guid}?notification=" . urlencode($url);
     return "<a href='{$notification_read}'>
	   <li {$viewed}> {$img} 
	   <div class='notfi-meta'> {$type}
	   <div class='data'>" . ossn_print("ossn:notifications:{$notif->type}", array(
          $user->fullname
     )) . '</div>
	   </div></li></a>';
}

ossn_register_callback('ossn', 'init', 'com_init');
