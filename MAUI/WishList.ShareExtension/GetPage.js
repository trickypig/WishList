// Safari runs this in the shared page before the extension opens, and hands the result to
// the extension under NSExtensionJavaScriptPreprocessingResultsKey. Stores that block our
// server still work, because the page came from the user's own browser.
var GetPage = function () {};

GetPage.prototype = {
    run: function (args) {
        args.completionFunction({
            url: document.URL,
            html: document.documentElement.outerHTML
        });
    },
    finalize: function (args) {}
};

var ExtensionPreprocessingJS = new GetPage();
