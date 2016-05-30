#!/usr/bin/env node
/**
 * Gather our URLs to test from the WPX file in our plugin directory and run it through the validator.
 *
 * from the plugin root dir on your server run 'node bin/run-amp-validator.js' to run
 */

'use strict';

function puts(error, stdout, stderr) {

    if ( error ) {
        console.log("Error:\r");
        console.log(error);
        return;
    }

    if ( stderr ) {
        console.log("stderr:\r");
        console.log(stderr);
        return;
    }
    console.log( stdout );
    //
    // var result = JSON.parse(stdout);
    //
    // for ( var key in result ) {
    //
    //     result = result[key];
    //
    //     if ( ! result.success ) {
    //
    //         console.log( "Errors for :"+key+"\n"+result.errors );
    //
    //     } else {
    //
    //         console.log( "Successfully Validated: "+key );
    //
    //     }
    //
    // }
}

/**
 * This parses our XML to gather the links to our posts so we can test them.
 * TODO: Better output and percent completed?
 *
 * We also might be able to utilize the BETA Node API listed here: https://github.com/ampproject/amphtml/tree/master/validator
 * could not get it to recognize the amp-validator require and ran into url.StartWith not a function errors.
 *
 * TODO: ask the user for their WP base URL
 */

function loadXMLDoc(filePath) {
    var fs = require('fs');
    var xml2js = require('xml2js');
    var urls = [];
    try {
        var fileData = fs.readFileSync(filePath, 'ascii');

        var parser = new xml2js.Parser();
        parser.parseString(fileData.substring(0, fileData.length), function (err, result) {

            var wptestBaseURL = result.rss.channel[0]['wp:base_site_url'],
                items = result.rss.channel[0].item;

            var postCount = 0;

            for (var i=0 , len = items.length; i< len; i++ ) {
                var item = items[i],
                    localBaseURL = 'http://auto-amp.dev',
                    postType = item['wp:post_type'][0],
                    postStatus = item['wp:status'][0],
                    postPassword = item['wp:post_password'][0];

                if ( 'post' === postType && 'publish' === postStatus && '' === postPassword ) {
                    postCount++;
                    var postDate = new Date( item['wp:post_date'] ),
                        link = item.link[0],
                        postMonth = ("0" + ( postDate.getMonth()+1 ) ).slice(-2),
                        postDay = ("0" + postDate.getDate()).slice(-2),
                        localURL = localBaseURL+"/"+postDate.getFullYear()+"/"+postMonth+"/"+postDay;

                    link = link.replace(wptestBaseURL, localURL)+"amp/";

                    urls.push( link );

                }
            }
            console.log("Hang tight, we are going to test "+postCount+" urls...");
        });

        return urls;
    } catch (ex) {console.log(ex)}
}

var exec = require('child_process').exec;

var XMLPath = "wptest.xml";
var testURLs = loadXMLDoc(XMLPath);

for (var i = 0, len = testURLs.length; i < len; i++) {
    var url = testURLs[i];
    var cmd = 'amp-validator ' + url;
    setTimeout(function(cmd) {
        exec(cmd, puts);
    }, i*2000,cmd);
}