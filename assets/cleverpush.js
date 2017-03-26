(function(c,l,v,r,p,s,h){c['CleverPushObject']=p;c[p]=c[p]||function(){(c[p].q=c[p].q||[]).push(arguments)},c[p].l=1*new Date();s=l.createElement(v),h=l.getElementsByTagName(v)[0];s.async=1;s.src=r;h.parentNode.insertBefore(s,h)})(window,document,'script','//' + cleverpushConfig.identifier + '.cleverpush.com/loader.js','cleverpush');

cleverpush('triggerOptIn', function(subscriptionIdParam) {
    var subscriptionId = subscriptionIdParam ? subscriptionIdParam : localStorage.getItem('push-subscription-id');
    console.log(subscriptionId);

    fetch(cleverpushConfig.ajaxUrl, {
        credentials: 'include',
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
        },
        body: 'action=cleverpush_subscription_id&subscriptionId=' + subscriptionId
    });
});
