this["ewd"] = this["ewd"] || {}; this["ewd"]["review"] =
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
/******/ 	return __webpack_require__(__webpack_require__.s = 150);
/******/ })
/************************************************************************/
/******/ ({

/***/ 0:
/***/ (function(module, exports) {

(function() { module.exports = this["wp"]["i18n"]; }());

/***/ }),

/***/ 11:
/***/ (function(module, exports) {

(function() { module.exports = this["wp"]["apiFetch"]; }());

/***/ }),

/***/ 147:
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ 150:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
// ESM COMPAT FLAG
__webpack_require__.r(__webpack_exports__);

// EXTERNAL MODULE: external "ReactDOM"
var external_ReactDOM_ = __webpack_require__(27);
var external_ReactDOM_default = /*#__PURE__*/__webpack_require__.n(external_ReactDOM_);

// CONCATENATED MODULE: ./node_modules/@wordpress/dom-ready/build-module/index.js
/**
 * @typedef {() => void} Callback
 *
 * TODO: Remove this typedef and inline `() => void` type.
 *
 * This typedef is used so that a descriptive type is provided in our
 * automatically generated documentation.
 *
 * An in-line type `() => void` would be preferable, but the generated
 * documentation is `null` in that case.
 *
 * @see https://github.com/WordPress/gutenberg/issues/18045
 */

/**
 * Specify a function to execute when the DOM is fully loaded.
 *
 * @param {Callback} callback A function to execute after the DOM is ready.
 *
 * @example
 * ```js
 * import domReady from '@wordpress/dom-ready';
 *
 * domReady( function() {
 * 	//do something after DOM loads.
 * } );
 * ```
 *
 * @return {void}
 */
function domReady(callback) {
  if (typeof document === 'undefined') {
    return;
  }

  if (document.readyState === 'complete' || // DOMContentLoaded + Images/Styles/etc loaded, so we call directly.
  document.readyState === 'interactive' // DOMContentLoaded fires at this point, so we call directly.
  ) {
    return void callback();
  } // DOMContentLoaded has not fired yet, delay callback until then.


  document.addEventListener('DOMContentLoaded', callback);
}
//# sourceMappingURL=index.js.map
// EXTERNAL MODULE: external "React"
var external_React_ = __webpack_require__(3);
var external_React_default = /*#__PURE__*/__webpack_require__.n(external_React_);

// EXTERNAL MODULE: external {"this":["wp","i18n"]}
var external_this_wp_i18n_ = __webpack_require__(0);

// EXTERNAL MODULE: external {"this":["wp","apiFetch"]}
var external_this_wp_apiFetch_ = __webpack_require__(11);
var external_this_wp_apiFetch_default = /*#__PURE__*/__webpack_require__.n(external_this_wp_apiFetch_);

// CONCATENATED MODULE: ./admin/vue/review/api/review.js
function _regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i["return"]) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }


var API_ROOT = 'easy-woocommerce-discounts/v1';
var applyReview = /*#__PURE__*/function () {
  var _ref = _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee(data) {
    var response, _t;
    return _regenerator().w(function (_context) {
      while (1) switch (_context.p = _context.n) {
        case 0:
          if (!(!data || !data.action || !data.action.length)) {
            _context.n = 1;
            break;
          }
          throw new Error(Object(external_this_wp_i18n_["__"])('Action is required.', 'easy-woocommerce-discounts'));
        case 1:
          _context.p = 1;
          _context.n = 2;
          return external_this_wp_apiFetch_default()({
            path: "".concat(API_ROOT, "/review"),
            method: 'POST',
            data: data
          });
        case 2:
          response = _context.v;
          if (!response) {
            _context.n = 3;
            break;
          }
          return _context.a(2, response);
        case 3:
          throw new Error(Object(external_this_wp_i18n_["__"])('There was an error on applying review.', 'easy-woocommerce-discounts'));
        case 4:
          _context.p = 4;
          _t = _context.v;
          throw _t;
        case 5:
          return _context.a(2);
      }
    }, _callee, null, [[1, 4]]);
  }));
  return function applyReview(_x) {
    return _ref.apply(this, arguments);
  };
}();
// EXTERNAL MODULE: ./admin/vue/review/components/Review/style.scss
var style = __webpack_require__(147);

// CONCATENATED MODULE: ./admin/vue/review/components/Review/index.jsx
function Review_regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return Review_regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i["return"]) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (Review_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, Review_regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, Review_regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), Review_regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", Review_regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), Review_regeneratorDefine2(u), Review_regeneratorDefine2(u, o, "Generator"), Review_regeneratorDefine2(u, n, function () { return this; }), Review_regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (Review_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function Review_regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } Review_regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { Review_regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, Review_regeneratorDefine2(e, r, n, t); }
function Review_asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function Review_asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { Review_asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { Review_asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
function _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t["return"] && (u = t["return"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(r) { if (Array.isArray(r)) return r; }




function Review() {
  var _useState = Object(external_React_["useState"])(true),
    _useState2 = _slicedToArray(_useState, 2),
    show = _useState2[0],
    setShow = _useState2[1];
  var later = /*#__PURE__*/function () {
    var _ref = Review_asyncToGenerator(/*#__PURE__*/Review_regenerator().m(function _callee(e) {
      var _t;
      return Review_regenerator().w(function (_context) {
        while (1) switch (_context.p = _context.n) {
          case 0:
            e.preventDefault();
            setShow(false);
            _context.p = 1;
            _context.n = 2;
            return applyReview({
              action: 'later'
            });
          case 2:
            _context.n = 4;
            break;
          case 3:
            _context.p = 3;
            _t = _context.v;
            console.error(_t);
          case 4:
            return _context.a(2);
        }
      }, _callee, null, [[1, 3]]);
    }));
    return function later(_x) {
      return _ref.apply(this, arguments);
    };
  }();
  var dismiss = /*#__PURE__*/function () {
    var _ref2 = Review_asyncToGenerator(/*#__PURE__*/Review_regenerator().m(function _callee2(e) {
      var _t2;
      return Review_regenerator().w(function (_context2) {
        while (1) switch (_context2.p = _context2.n) {
          case 0:
            e.preventDefault();
            setShow(false);
            _context2.p = 1;
            _context2.n = 2;
            return applyReview({
              action: 'dismiss'
            });
          case 2:
            _context2.n = 4;
            break;
          case 3:
            _context2.p = 3;
            _t2 = _context2.v;
            console.error(_t2);
          case 4:
            return _context2.a(2);
        }
      }, _callee2, null, [[1, 3]]);
    }));
    return function dismiss(_x2) {
      return _ref2.apply(this, arguments);
    };
  }();
  if (!show) {
    return null;
  }
  return /*#__PURE__*/external_React_default.a.createElement("div", {
    className: "asnp-review"
  }, /*#__PURE__*/external_React_default.a.createElement("p", {
    dangerouslySetInnerHTML: {
      __html: Object(external_this_wp_i18n_["sprintf"])(Object(external_this_wp_i18n_["__"])("We hope you're enjoying %1$s! %3$s Could you please do us a BIG favor and give it a %2$s to help us spread the word and boost our motivation?%4$s %5$sShare your feature requests%6$s with the review, We always check them and try our best.", 'easy-woocommerce-discounts'), '<a href="https://www.asanaplugins.com/product/woocommerce-dynamic-pricing-and-discounts-plugin/" target="_blank"><strong>Discount Rules and Dynamic Pricing for WooCommerce</strong></a>', '<a href="https://wordpress.org/plugins/easy-woocommerce-discounts/reviews/" target="_blank"><strong>5-star rating on WordPress</strong></a>', '<img draggable="false" role="img" width="20" height="20" className="emoji" alt="💕" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f495.svg">', '<br/>', '<b>', '</b>')
    }
  }), /*#__PURE__*/external_React_default.a.createElement("ul", null, /*#__PURE__*/external_React_default.a.createElement("li", {
    style: {
      margin: '0 15px 0 0'
    },
    className: "notice-link-item"
  }, /*#__PURE__*/external_React_default.a.createElement("a", {
    href: "https://wordpress.org/plugins/easy-woocommerce-discounts/reviews/",
    target: "_blank"
  }, /*#__PURE__*/external_React_default.a.createElement("span", {
    style: {
      marginRight: '5px'
    },
    className: "dashicons dashicons-external"
  }), Object(external_this_wp_i18n_["__"])('OK, you deserve it!', 'easy-woocommerce-discounts'))), /*#__PURE__*/external_React_default.a.createElement("li", {
    style: {
      margin: '0 15px 0 0'
    },
    className: "notice-link-item"
  }, /*#__PURE__*/external_React_default.a.createElement("a", {
    href: "#",
    target: "_blank",
    onClick: dismiss
  }, /*#__PURE__*/external_React_default.a.createElement("span", {
    style: {
      marginRight: '5px'
    },
    className: "dashicons dashicons-smiley"
  }), Object(external_this_wp_i18n_["__"])('I already did', 'easy-woocommerce-discounts'))), /*#__PURE__*/external_React_default.a.createElement("li", {
    style: {
      margin: '0 15px 0 0'
    },
    className: "notice-link-item"
  }, /*#__PURE__*/external_React_default.a.createElement("a", {
    href: "#",
    className: "dismiss-btn",
    target: "_blank",
    "data-later": "1",
    onClick: later
  }, /*#__PURE__*/external_React_default.a.createElement("span", {
    style: {
      marginRight: '5px'
    },
    className: "dashicons dashicons-calendar-alt"
  }), Object(external_this_wp_i18n_["__"])('Maybe Later', 'easy-woocommerce-discounts'))), /*#__PURE__*/external_React_default.a.createElement("li", {
    style: {
      margin: '0 15px 0 0'
    },
    className: "notice-link-item"
  }, /*#__PURE__*/external_React_default.a.createElement("a", {
    href: "https://wordpress.org/support/plugin/easy-woocommerce-discounts/",
    target: "_blank"
  }, /*#__PURE__*/external_React_default.a.createElement("span", {
    style: {
      marginRight: '5px'
    },
    className: "dashicons dashicons-sos"
  }), Object(external_this_wp_i18n_["__"])('I need help', 'easy-woocommerce-discounts'))), /*#__PURE__*/external_React_default.a.createElement("li", {
    style: {
      margin: '0 15px 0 0'
    },
    className: "notice-link-item"
  }, /*#__PURE__*/external_React_default.a.createElement("a", {
    href: "#",
    target: "_blank",
    onClick: dismiss
  }, /*#__PURE__*/external_React_default.a.createElement("span", {
    style: {
      marginRight: '5px'
    },
    className: "dashicons dashicons-dismiss"
  }), Object(external_this_wp_i18n_["__"])('Never show again', 'easy-woocommerce-discounts')))), /*#__PURE__*/external_React_default.a.createElement("button", {
    type: "button",
    className: "notice-dismiss",
    title: Object(external_this_wp_i18n_["__"])('Maybe Later', 'easy-woocommerce-discounts'),
    onClick: later
  }, /*#__PURE__*/external_React_default.a.createElement("span", {
    className: "screen-reader-text"
  }, Object(external_this_wp_i18n_["__"])('Maybe Later', 'easy-woocommerce-discounts'))));
}
// CONCATENATED MODULE: ./admin/vue/review/index.jsx



var createElement = function createElement() {
  var heading = document.querySelector('#wpbody .wrap h1');
  if (!heading) {
    return null;
  }
  var element = document.createElement('div');
  element.classList.add('asnp-review-container');
  heading.after(element);
  return element;
};
domReady(function () {
  var element = createElement();
  if (!element) {
    return;
  }
  if ('function' === typeof external_ReactDOM_default.a.createRoot) {
    external_ReactDOM_default.a.createRoot(element).render(/*#__PURE__*/React.createElement(Review, null));
  } else {
    external_ReactDOM_default.a.render(/*#__PURE__*/React.createElement(Review, null), element);
  }
});

/***/ }),

/***/ 27:
/***/ (function(module, exports) {

(function() { module.exports = this["ReactDOM"]; }());

/***/ }),

/***/ 3:
/***/ (function(module, exports) {

(function() { module.exports = this["React"]; }());

/***/ })

/******/ });