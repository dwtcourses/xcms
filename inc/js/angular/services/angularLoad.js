// Generated by CoffeeScript 1.8.0
angular.module('angularLoad', []).service('angularLoad', [
  '$document', '$q', '$timeout', function($document, $q, $timeout) {

    /**
     * Dynamically loads the given script
     * @param src The url of the script to load dynamically
     * @returns {*} Promise that will be resolved once the script has been loaded.
     */
    var loader;
    loader = function(createElement) {
      var promises;
      promises = {};
      return function(url) {
        var deferred, element, ext;
        ext = url.split('.').pop();
        if (typeof promises[url] === 'undefined' || ext !== "css") {
          deferred = $q.defer();
          element = createElement(url);
          element.onload = element.onreadystatechange = function(e) {
            $timeout(function() {
              deferred.resolve(e);
            });
          };
          element.onerror = function(e) {
            $timeout(function() {
              deferred.reject(e);
            });
          };
          if (ext === "css") {
            promises[url] = deferred.promise;
          } else {
            return deferred.promise;
          }
        }
        return promises[url];
      };
    };
    this.loadScript = loader(function(src) {
      var script;
      script = $document[0].createElement('script');
      script.src = src;
      $document[0].body.appendChild(script);
      return script;
    });

    /**
     * Dynamically loads the given CSS file
     * @param href The url of the CSS to load dynamically
     * @returns {*} Promise that will be resolved once the CSS file has been loaded.
     */
    this.loadCSS = loader(function(href) {
      var style;
      style = $document[0].createElement('link');
      style.rel = 'stylesheet';
      style.type = 'text/css';
      style.href = href;
      $document[0].head.appendChild(style);
      return style;
    });
  }
]);
