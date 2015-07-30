// Generated by CoffeeScript 1.8.0
angular.module("ximdex.common.service").factory("xTabs", [
  "$window", "$timeout", "$http", "xUrlHelper", "$rootScope", "$compile", "angularLoad", function($window, $timeout, $http, xUrlHelper, $rootScope, $compile, angularLoad) {
    var activeIndex, bindFormEvents, postLoadCssAndJs, postLoadJs, scopeWelcomeTab, tabs, triggerUpdateTabsPosition, visitedTabs, xtab;
    scopeWelcomeTab = null;
    tabs = [];
    visitedTabs = [];
    activeIndex = -1;
    xtab = {};
    bindFormEvents = function(tab) {
      var form, forms, gobackButton, i, _i, _len;
      forms = angular.element("form", "#" + tab.id + "_content");
      if (forms.length === 0) {
        new X.FormsManager({
          actionView: {
            action: tab.action
          },
          tabId: tab.id,
          actionContainer: angular.element("#" + tab.id + "_content")
        });
      } else {
        for (i = _i = 0, _len = forms.length; _i < _len; i = ++_i) {
          form = forms[i];
          new X.FormsManager({
            actionView: {
              action: tab.action
            },
            tabId: tab.id,
            actionContainer: angular.element("#" + tab.id + "_content"),
            form: angular.element(form)
          });
        }
      }
      gobackButton = angular.element('fieldset.buttons-form .goback-button', "#" + tab.id + "_content");
      gobackButton.bind("click", function() {
        tab.history.pop();
        tab.url = tab.history[tab.history.length - 1];
        return xtab.reloadTabById(tab.id);
      });
    };
    xtab.getTabIndex = function(tabId) {
      var i, tab, _i, _len;
      for (i = _i = 0, _len = tabs.length; _i < _len; i = ++_i) {
        tab = tabs[i];
        if (tab.id === tabId) {
          return i;
        }
      }
      return -1;
    };
    xtab.submitForm = function(args) {
      $http({
        url: args.url,
        responseType: args.reload ? "" : "json",
        method: "POST",
        data: args.data,
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        }
      }).success(function(data) {
        var index;
        if (data) {
          index = xtab.getTabIndex(args.tabId);
          if (index < 0) {
            return;
          }
          tabs[index].history.push(args.url);
          tabs[index].url = args.url;
          if (args.reload === true) {
            tabs[index].content = data;
            xtab.loadCssAndJs(tabs[index]);
          }
          if (args.callback) {
            args.callback({
              data: data,
              tab: tabs[index]
            });
          }
        }
      }).error(function(error) {
        if (args.callback) {
          args.callback({
            error: true
          });
        }
      });
    };
    postLoadJs = function(tab, nodeids) {
      var compiled, container, scope;
      container = angular.element("#" + tab.id + "_content");
      if (tab.id !== "10000_welcome") {
        scope = container.scope();
      } else {
        if (scopeWelcomeTab != null) {
          scopeWelcomeTab.$destroy();
        }
        scope = container.scope().$new();
      }
      compiled = $compile(tab.content)(scope);
      if (tab.id === "10000_welcome") {
        scopeWelcomeTab = scope;
      }
      container.html(compiled);
      bindFormEvents(tab);
      return $window.com.ximdex.triggerActionLoaded({
        title: "#" + tab.id + "_tab",
        context: "#" + tab.id + "_content",
        url: tab.url,
        action: tab.action,
        nodes: nodeids,
        tab: tab
      });
    };
    postLoadCssAndJs = function(tab) {
      var callback, cont, content, css, cssArr, js, jsArr, n, nodeids, _i, _j, _k, _len, _len1, _len2, _ref, _results;
      cssArr = [];
      content = angular.element(tab.content);
      content.first().children().each(function(index, item) {
        cssArr.push(angular.element(item).html());
      });
      for (_i = 0, _len = cssArr.length; _i < _len; _i++) {
        css = cssArr[_i];
        angularLoad.loadCSS(css);
      }
      jsArr = [];
      content.first().next().children().each(function(index, item) {
        jsArr.push(angular.element(item).html());
      });
      nodeids = [];
      _ref = tab.nodes;
      for (_j = 0, _len1 = _ref.length; _j < _len1; _j++) {
        n = _ref[_j];
        nodeids.push(n.nodeid);
      }
      cont = 0;
      callback = function() {
        if (++cont === jsArr.length) {
          postLoadJs(tab, nodeids);
        }
      };
      if (jsArr.length > 0) {
        _results = [];
        for (_k = 0, _len2 = jsArr.length; _k < _len2; _k++) {
          js = jsArr[_k];
          _results.push(angularLoad.loadScript(js).then(function() {
            callback();
          })["catch"](function() {
            console.log("Error loading JS");
          }));
        }
        return _results;
      } else {
        return postLoadJs(tab, nodeids);
      }
    };
    xtab.loadCssAndJs = function(tab) {
      $timeout(function() {
        return postLoadCssAndJs(tab);
      }, 0);
    };
    xtab.pushTab = function(action, nodes) {
      var i, n, newid, tab, url, _i, _j, _len, _len1;
      newid = "";
      for (_i = 0, _len = nodes.length; _i < _len; _i++) {
        n = nodes[_i];
        newid += n.nodeid + "_";
      }
      newid += action.command;
      for (i = _j = 0, _len1 = tabs.length; _j < _len1; i = ++_j) {
        tab = tabs[i];
        if (tab.id === newid) {
          xtab.setActiveTab(i);
          xtab.highlightTab(i);
          return;
        }
      }
      url = xUrlHelper.getAction({
        action: action.command,
        nodes: nodes,
        module: action.module,
        method: action.method,
        options: action.params
      });
      $http.get(url).success(function(data) {
        var newlength, newtab;
        if (data) {
          newtab = {
            id: newid,
            name: action.name,
            content: data,
            nodes: nodes,
            action: action,
            command: action.command,
            blink: false,
            show: true,
            url: url,
            history: [url]
          };
          xtab.loadCssAndJs(newtab);
          newlength = tabs.push(newtab);

          /*$timeout(
              () ->
                  $rootScope.$broadcast('updateTabsPosition')
          ,
              0
          )
           */
          xtab.setActiveTab(newlength - 1);
        }
      });
    };
    xtab.getTabs = function() {
      return tabs;
    };
    xtab.activeIndex = function() {
      return activeIndex;
    };
    triggerUpdateTabsPosition = function(deletedTab) {
      if (deletedTab != null) {
        return $rootScope.$broadcast('updateTabsPosition', deletedTab);
      } else {
        return $rootScope.$broadcast('updateTabsPosition');
      }
    };
    xtab.removeTab = function(index) {
      var deletedTab, i, tab, visitedIndex, _i, _len;
      visitedIndex = visitedTabs.indexOf(index);
      if (visitedIndex >= 0) {
        visitedTabs.splice(visitedIndex, 1);
        for (i = _i = 0, _len = visitedTabs.length; _i < _len; i = ++_i) {
          tab = visitedTabs[i];
          if (visitedTabs[i] > index) {
            visitedTabs[i] = visitedTabs[i] - 1;
          }
        }
      }
      deletedTab = (tabs.splice(index, 1))[0];
      if (visitedTabs.length > 0) {
        activeIndex = visitedTabs[0];
        $timeout(function() {
          return triggerUpdateTabsPosition(deletedTab);
        }, 0);
      } else {
        activeIndex = -1;
      }

      /*$timeout(
          () ->
              $rootScope.$broadcast('updateTabsPosition')
      ,
          400
      )
       */
    };
    xtab.setActiveTab = function(index) {
      var visitedIndex;
      activeIndex = index;
      visitedIndex = visitedTabs.indexOf(index);
      if (visitedIndex >= 0) {
        visitedTabs.splice(visitedIndex, 1);
      }
      visitedTabs.unshift(index);
      $timeout(triggerUpdateTabsPosition, 0);
    };
    xtab.highlightTab = function(index) {
      if (tabs[index].blink === true) {
        return;
      }
      tabs[index].blink = true;
      return $timeout(function() {
        return tabs[index].blink = false;
      }, 2000);
    };
    xtab.closeAllTabs = function() {
      tabs.splice(0, tabs.length);
      activeIndex = -1;
      visitedTabs = [];
      $timeout(triggerUpdateTabsPosition, 400);
    };
    xtab.offAllTabs = function() {
      activeIndex = -1;
    };
    xtab.removeTabById = function(tabId) {
      var index;
      index = xtab.getTabIndex(tabId);
      if (index >= 0) {
        return xtab.removeTab(index);
      }
    };
    xtab.reloadTab = function(index) {
      var tab, url;
      tab = tabs[index];
      url = xUrlHelper.getAction({
        action: tab.action.command,
        nodes: tab.nodes,
        module: tab.action.module,
        method: tab.action.method,
        options: [
          {
            actionReload: true
          }
        ]
      });
      $http.get(url).success(function(data) {
        if (data) {
          tab.content = data;
          xtab.loadCssAndJs(tab);
        }
      });
    };
    xtab.reloadTabById = function(tabId) {
      var index;
      index = xtab.getTabIndex(tabId);
      if (index >= 0) {
        xtab.reloadTab(index);
      }
    };
    xtab.setTabNode = function(tabId, nodes) {
      var index;
      index = xtab.getTabIndex(tabId);
      if (index >= 0) {
        tabs[index].nodes = nodes;
      }
    };
    xtab.setActiveTabById = function(tabId) {
      var index;
      index = xtab.getTabIndex(tabId);
      if (index >= 0) {
        xtab.setActiveTab(index);
      }
    };
    xtab.getActiveTab = function() {
      if (activeIndex >= 0) {
        return tabs[activeIndex];
      }
      return null;
    };
    xtab.openAction = function(action, nodes) {
      var n, newNode, nodesArray, _i, _len;
      nodesArray = [];
      if (Array.isArray(nodes)) {
        for (_i = 0, _len = nodes.length; _i < _len; _i++) {
          n = nodes[_i];
          newNode = {
            nodeid: n
          };
          nodesArray.push(newNode);
        }
      } else if (nodes) {
        nodesArray.push({
          nodeid: nodes
        });
      }
      xtab.pushTab(action, nodesArray);
    };
    return xtab;
  }
]);