CleverPush = window.CleverPush || [];

CleverPush.push(['init', {
    channelId: cleverpushWordpressConfig.channelId,
    autoRegister: false
}]);

CleverPush.push(['triggerOptIn', function(err, subscriptionId) {
    if (subscriptionId) {
        fetch(cleverpushWordpressConfig.ajaxUrl, {
            credentials: 'include',
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
            },
            body: 'action=cleverpush_subscription_id&subscriptionId=' + subscriptionId
        });
    } else {
        if (err) {
            console.error('CleverPush:', err);
        } else {
            console.error('CleverPush: subscription ID not found');
        }
    }
}]);
