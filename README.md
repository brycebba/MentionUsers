**OSSN MENTION USERS COMPONENT**

This component enables a user to mention other users in posts and comments by using the syntax @Full Name and that will send the mentioned user a notification telling them that they were mentioned in a post or comment with a link to that item.

This can be used in conjunction with the Display Username component as of v1.2 if you want to display usernames and mention @username instead of display Full Name and mention @Full Name. It will dynamically switch based whether or not the Display Username component is installed and turned on. You can find more info on it here: https://www.opensource-socialnetwork.org/component/view/3065/display-username

-When mentioning usernames using the Display Username component the exact casing must be used to match the user.
-When mentioning user full names the casing doesnt matter since that is not something that guarantees uniqueness.
-Mentions are not limited to the friends of the user, the entire user base can be mentioned
-Multiple mentions can be done in a post or comment. Be aware, if you duplicate mentions they will get a duplicate notification

**TO INSTALL**
Zip the MentionUsers subdirectory into a .zip to install as a package/component in OSSN