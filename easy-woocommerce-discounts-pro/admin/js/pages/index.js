this["ewd"] = this["ewd"] || {}; this["ewd"]["pages"] =
/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 151);
/******/ })
/************************************************************************/
/******/ ({

/***/ 151:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXPORTS
__webpack_require__.d(__webpack_exports__, "registerRoute", function() { return /* binding */ src_registerRoute; });
__webpack_require__.d(__webpack_exports__, "registerTab", function() { return /* binding */ src_registerTab; });
__webpack_require__.d(__webpack_exports__, "getTabComponents", function() { return /* binding */ src_getTabComponents; });
__webpack_require__.d(__webpack_exports__, "getRoutes", function() { return /* binding */ src_getRoutes; });
__webpack_require__.d(__webpack_exports__, "getTabs", function() { return /* binding */ src_getTabs; });

// EXTERNAL MODULE: external "Vue"
var external_Vue_ = __webpack_require__(4);
var external_Vue_default = /*#__PURE__*/__webpack_require__.n(external_Vue_);

// EXTERNAL MODULE: external "Vuex"
var external_Vuex_ = __webpack_require__(7);
var external_Vuex_default = /*#__PURE__*/__webpack_require__.n(external_Vuex_);

// CONCATENATED MODULE: ./admin/vue/pages/src/store/index.js


external_Vue_default.a.use(external_Vuex_default.a);
/* harmony default export */ var store = (new external_Vuex_default.a.Store({
  state: {
    routes: [],
    tabs: []
  },
  mutations: {
    addRoute: function addRoute(state, route) {
      return state.routes.push(route);
    },
    addTab: function addTab(state, tab) {
      return state.tabs.push(tab);
    }
  },
  actions: {
    addRoute: function addRoute(_ref, route) {
      var commit = _ref.commit;
      if (!route || !route.path) {
        throw new Error('Route path is required.');
      } else if (!route.component) {
        throw new Error('Route component is required.');
      }
      commit('addRoute', route);
    },
    addTab: function addTab(_ref2, tab) {
      var commit = _ref2.commit;
      if (!tab || !tab.name) {
        throw new Error('Tab name is required.');
      } else if (!tab.component) {
        throw new Error('Tab component is required.');
      }
      commit('addTab', tab);
    }
  },
  getters: {
    getRoutes: function getRoutes(state) {
      return state.routes;
    },
    getTabs: function getTabs(state) {
      return state.tabs;
    },
    getTabComponents: function getTabComponents(state) {
      var components = {};
      state.tabs.map(function (tab) {
        if (tab.component && tab.component.name) {
          components[tab.component.name] = tab.component;
        }
      });
      return components;
    }
  }
}));
// CONCATENATED MODULE: ./admin/vue/pages/src/index.js

var src_registerRoute = function registerRoute(route) {
  if (!route || !route.path) {
    throw new Error('Route path is required.');
  } else if (!route.component) {
    throw new Error('Route component is required.');
  }
  store.dispatch('addRoute', route);
};
var src_registerTab = function registerTab(tab) {
  if (!tab || !tab.name) {
    throw new Error('Tab name is required.');
  } else if (!tab.route) {
    throw new Error('Tab route is required.');
  }
  store.dispatch('addTab', tab);
};
var src_getTabComponents = function getTabComponents() {
  return store.getters.getTabComponents;
};
var src_getRoutes = function getRoutes() {
  return store.getters.getRoutes;
};
var src_getTabs = function getTabs() {
  return store.getters.getTabs;
};

/***/ }),

/***/ 4:
/***/ (function(module, exports) {

(function() { module.exports = this["Vue"]; }());

/***/ }),

/***/ 7:
/***/ (function(module, exports) {

(function() { module.exports = this["Vuex"]; }());

/***/ })

/******/ });