/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};

/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {

/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId])
/******/ 			return installedModules[moduleId].exports;

/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			exports: {},
/******/ 			id: moduleId,
/******/ 			loaded: false
/******/ 		};

/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);

/******/ 		// Flag the module as loaded
/******/ 		module.loaded = true;

/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}


/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;

/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;

/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/__build__/";

/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(0);
/******/ })
/************************************************************************/
/******/ ([
/* 0 */
/***/ function(module, exports, __webpack_require__) {

	__webpack_require__(1);
	module.exports = __webpack_require__(73);


/***/ },
/* 1 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(__resourceQuery) {var url = __webpack_require__(2);
	var SockJS = __webpack_require__(8);
	var stripAnsi = __webpack_require__(71);
	var scriptElements = document.getElementsByTagName("script");
	var scriptHost = scriptElements[scriptElements.length-1].getAttribute("src").replace(/\/[^\/]+$/, "");

	// If this bundle is inlined, use the resource query to get the correct url.
	// Else, get the url from the <script> this file was called with.
	var urlParts = url.parse( true ?
		__resourceQuery.substr(1) :
		(scriptHost ? scriptHost : "/")
	);

	var sock = null;
	var hot = false;
	var initial = true;
	var currentHash = "";

	var onSocketMsg = {
		hot: function() {
			hot = true;
			console.log("[WDS] Hot Module Replacement enabled.");
		},
		invalid: function() {
			console.log("[WDS] App updated. Recompiling...");
		},
		hash: function(hash) {
			currentHash = hash;
		},
		"still-ok": function() {
			console.log("[WDS] Nothing changed.")
		},
		ok: function() {
			if(initial) return initial = false;
			reloadApp();
		},
		warnings: function(warnings) {
			console.log("[WDS] Warnings while compiling.");
			for(var i = 0; i < warnings.length; i++)
				console.warn(stripAnsi(warnings[i]));
			if(initial) return initial = false;
			reloadApp();
		},
		errors: function(errors) {
			console.log("[WDS] Errors while compiling.");
			for(var i = 0; i < errors.length; i++)
				console.error(stripAnsi(errors[i]));
			if(initial) return initial = false;
			reloadApp();
		},
		"proxy-error": function(errors) {
			console.log("[WDS] Proxy error.");
			for(var i = 0; i < errors.length; i++)
				console.error(stripAnsi(errors[i]));
			if(initial) return initial = false;
			reloadApp();
		}
	};

	var newConnection = function() {
		sock = new SockJS(url.format({
			protocol: urlParts.protocol,
			auth: urlParts.auth,
			hostname: (urlParts.hostname === '0.0.0.0') ? window.location.hostname : urlParts.hostname,
			port: urlParts.port,
			pathname: urlParts.path === '/' ? "/sockjs-node" : urlParts.path
		}));

		sock.onclose = function() {
			console.error("[WDS] Disconnected!");

			// Try to reconnect.
			sock = null;
			setTimeout(function () {
				newConnection();
			}, 2000);
		};

		sock.onmessage = function(e) {
			// This assumes that all data sent via the websocket is JSON.
			var msg = JSON.parse(e.data);
			onSocketMsg[msg.type](msg.data);
		};
	};

	newConnection();

	function reloadApp() {
		if(hot) {
			console.log("[WDS] App hot update...");
			window.postMessage("webpackHotUpdate" + currentHash, "*");
		} else {
			console.log("[WDS] App updated. Reloading...");
			window.location.reload();
		}
	}

	/* WEBPACK VAR INJECTION */}.call(exports, "?http://localhost:8080"))

/***/ },
/* 2 */
/***/ function(module, exports, __webpack_require__) {

	// Copyright Joyent, Inc. and other Node contributors.
	//
	// Permission is hereby granted, free of charge, to any person obtaining a
	// copy of this software and associated documentation files (the
	// "Software"), to deal in the Software without restriction, including
	// without limitation the rights to use, copy, modify, merge, publish,
	// distribute, sublicense, and/or sell copies of the Software, and to permit
	// persons to whom the Software is furnished to do so, subject to the
	// following conditions:
	//
	// The above copyright notice and this permission notice shall be included
	// in all copies or substantial portions of the Software.
	//
	// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
	// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
	// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
	// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
	// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
	// USE OR OTHER DEALINGS IN THE SOFTWARE.

	var punycode = __webpack_require__(3);

	exports.parse = urlParse;
	exports.resolve = urlResolve;
	exports.resolveObject = urlResolveObject;
	exports.format = urlFormat;

	exports.Url = Url;

	function Url() {
	  this.protocol = null;
	  this.slashes = null;
	  this.auth = null;
	  this.host = null;
	  this.port = null;
	  this.hostname = null;
	  this.hash = null;
	  this.search = null;
	  this.query = null;
	  this.pathname = null;
	  this.path = null;
	  this.href = null;
	}

	// Reference: RFC 3986, RFC 1808, RFC 2396

	// define these here so at least they only have to be
	// compiled once on the first module load.
	var protocolPattern = /^([a-z0-9.+-]+:)/i,
	    portPattern = /:[0-9]*$/,

	    // RFC 2396: characters reserved for delimiting URLs.
	    // We actually just auto-escape these.
	    delims = ['<', '>', '"', '`', ' ', '\r', '\n', '\t'],

	    // RFC 2396: characters not allowed for various reasons.
	    unwise = ['{', '}', '|', '\\', '^', '`'].concat(delims),

	    // Allowed by RFCs, but cause of XSS attacks.  Always escape these.
	    autoEscape = ['\''].concat(unwise),
	    // Characters that are never ever allowed in a hostname.
	    // Note that any invalid chars are also handled, but these
	    // are the ones that are *expected* to be seen, so we fast-path
	    // them.
	    nonHostChars = ['%', '/', '?', ';', '#'].concat(autoEscape),
	    hostEndingChars = ['/', '?', '#'],
	    hostnameMaxLen = 255,
	    hostnamePartPattern = /^[a-z0-9A-Z_-]{0,63}$/,
	    hostnamePartStart = /^([a-z0-9A-Z_-]{0,63})(.*)$/,
	    // protocols that can allow "unsafe" and "unwise" chars.
	    unsafeProtocol = {
	      'javascript': true,
	      'javascript:': true
	    },
	    // protocols that never have a hostname.
	    hostlessProtocol = {
	      'javascript': true,
	      'javascript:': true
	    },
	    // protocols that always contain a // bit.
	    slashedProtocol = {
	      'http': true,
	      'https': true,
	      'ftp': true,
	      'gopher': true,
	      'file': true,
	      'http:': true,
	      'https:': true,
	      'ftp:': true,
	      'gopher:': true,
	      'file:': true
	    },
	    querystring = __webpack_require__(5);

	function urlParse(url, parseQueryString, slashesDenoteHost) {
	  if (url && isObject(url) && url instanceof Url) return url;

	  var u = new Url;
	  u.parse(url, parseQueryString, slashesDenoteHost);
	  return u;
	}

	Url.prototype.parse = function(url, parseQueryString, slashesDenoteHost) {
	  if (!isString(url)) {
	    throw new TypeError("Parameter 'url' must be a string, not " + typeof url);
	  }

	  var rest = url;

	  // trim before proceeding.
	  // This is to support parse stuff like "  http://foo.com  \n"
	  rest = rest.trim();

	  var proto = protocolPattern.exec(rest);
	  if (proto) {
	    proto = proto[0];
	    var lowerProto = proto.toLowerCase();
	    this.protocol = lowerProto;
	    rest = rest.substr(proto.length);
	  }

	  // figure out if it's got a host
	  // user@server is *always* interpreted as a hostname, and url
	  // resolution will treat //foo/bar as host=foo,path=bar because that's
	  // how the browser resolves relative URLs.
	  if (slashesDenoteHost || proto || rest.match(/^\/\/[^@\/]+@[^@\/]+/)) {
	    var slashes = rest.substr(0, 2) === '//';
	    if (slashes && !(proto && hostlessProtocol[proto])) {
	      rest = rest.substr(2);
	      this.slashes = true;
	    }
	  }

	  if (!hostlessProtocol[proto] &&
	      (slashes || (proto && !slashedProtocol[proto]))) {

	    // there's a hostname.
	    // the first instance of /, ?, ;, or # ends the host.
	    //
	    // If there is an @ in the hostname, then non-host chars *are* allowed
	    // to the left of the last @ sign, unless some host-ending character
	    // comes *before* the @-sign.
	    // URLs are obnoxious.
	    //
	    // ex:
	    // http://a@b@c/ => user:a@b host:c
	    // http://a@b?@c => user:a host:c path:/?@c

	    // v0.12 TODO(isaacs): This is not quite how Chrome does things.
	    // Review our test case against browsers more comprehensively.

	    // find the first instance of any hostEndingChars
	    var hostEnd = -1;
	    for (var i = 0; i < hostEndingChars.length; i++) {
	      var hec = rest.indexOf(hostEndingChars[i]);
	      if (hec !== -1 && (hostEnd === -1 || hec < hostEnd))
	        hostEnd = hec;
	    }

	    // at this point, either we have an explicit point where the
	    // auth portion cannot go past, or the last @ char is the decider.
	    var auth, atSign;
	    if (hostEnd === -1) {
	      // atSign can be anywhere.
	      atSign = rest.lastIndexOf('@');
	    } else {
	      // atSign must be in auth portion.
	      // http://a@b/c@d => host:b auth:a path:/c@d
	      atSign = rest.lastIndexOf('@', hostEnd);
	    }

	    // Now we have a portion which is definitely the auth.
	    // Pull that off.
	    if (atSign !== -1) {
	      auth = rest.slice(0, atSign);
	      rest = rest.slice(atSign + 1);
	      this.auth = decodeURIComponent(auth);
	    }

	    // the host is the remaining to the left of the first non-host char
	    hostEnd = -1;
	    for (var i = 0; i < nonHostChars.length; i++) {
	      var hec = rest.indexOf(nonHostChars[i]);
	      if (hec !== -1 && (hostEnd === -1 || hec < hostEnd))
	        hostEnd = hec;
	    }
	    // if we still have not hit it, then the entire thing is a host.
	    if (hostEnd === -1)
	      hostEnd = rest.length;

	    this.host = rest.slice(0, hostEnd);
	    rest = rest.slice(hostEnd);

	    // pull out port.
	    this.parseHost();

	    // we've indicated that there is a hostname,
	    // so even if it's empty, it has to be present.
	    this.hostname = this.hostname || '';

	    // if hostname begins with [ and ends with ]
	    // assume that it's an IPv6 address.
	    var ipv6Hostname = this.hostname[0] === '[' &&
	        this.hostname[this.hostname.length - 1] === ']';

	    // validate a little.
	    if (!ipv6Hostname) {
	      var hostparts = this.hostname.split(/\./);
	      for (var i = 0, l = hostparts.length; i < l; i++) {
	        var part = hostparts[i];
	        if (!part) continue;
	        if (!part.match(hostnamePartPattern)) {
	          var newpart = '';
	          for (var j = 0, k = part.length; j < k; j++) {
	            if (part.charCodeAt(j) > 127) {
	              // we replace non-ASCII char with a temporary placeholder
	              // we need this to make sure size of hostname is not
	              // broken by replacing non-ASCII by nothing
	              newpart += 'x';
	            } else {
	              newpart += part[j];
	            }
	          }
	          // we test again with ASCII char only
	          if (!newpart.match(hostnamePartPattern)) {
	            var validParts = hostparts.slice(0, i);
	            var notHost = hostparts.slice(i + 1);
	            var bit = part.match(hostnamePartStart);
	            if (bit) {
	              validParts.push(bit[1]);
	              notHost.unshift(bit[2]);
	            }
	            if (notHost.length) {
	              rest = '/' + notHost.join('.') + rest;
	            }
	            this.hostname = validParts.join('.');
	            break;
	          }
	        }
	      }
	    }

	    if (this.hostname.length > hostnameMaxLen) {
	      this.hostname = '';
	    } else {
	      // hostnames are always lower case.
	      this.hostname = this.hostname.toLowerCase();
	    }

	    if (!ipv6Hostname) {
	      // IDNA Support: Returns a puny coded representation of "domain".
	      // It only converts the part of the domain name that
	      // has non ASCII characters. I.e. it dosent matter if
	      // you call it with a domain that already is in ASCII.
	      var domainArray = this.hostname.split('.');
	      var newOut = [];
	      for (var i = 0; i < domainArray.length; ++i) {
	        var s = domainArray[i];
	        newOut.push(s.match(/[^A-Za-z0-9_-]/) ?
	            'xn--' + punycode.encode(s) : s);
	      }
	      this.hostname = newOut.join('.');
	    }

	    var p = this.port ? ':' + this.port : '';
	    var h = this.hostname || '';
	    this.host = h + p;
	    this.href += this.host;

	    // strip [ and ] from the hostname
	    // the host field still retains them, though
	    if (ipv6Hostname) {
	      this.hostname = this.hostname.substr(1, this.hostname.length - 2);
	      if (rest[0] !== '/') {
	        rest = '/' + rest;
	      }
	    }
	  }

	  // now rest is set to the post-host stuff.
	  // chop off any delim chars.
	  if (!unsafeProtocol[lowerProto]) {

	    // First, make 100% sure that any "autoEscape" chars get
	    // escaped, even if encodeURIComponent doesn't think they
	    // need to be.
	    for (var i = 0, l = autoEscape.length; i < l; i++) {
	      var ae = autoEscape[i];
	      var esc = encodeURIComponent(ae);
	      if (esc === ae) {
	        esc = escape(ae);
	      }
	      rest = rest.split(ae).join(esc);
	    }
	  }


	  // chop off from the tail first.
	  var hash = rest.indexOf('#');
	  if (hash !== -1) {
	    // got a fragment string.
	    this.hash = rest.substr(hash);
	    rest = rest.slice(0, hash);
	  }
	  var qm = rest.indexOf('?');
	  if (qm !== -1) {
	    this.search = rest.substr(qm);
	    this.query = rest.substr(qm + 1);
	    if (parseQueryString) {
	      this.query = querystring.parse(this.query);
	    }
	    rest = rest.slice(0, qm);
	  } else if (parseQueryString) {
	    // no query string, but parseQueryString still requested
	    this.search = '';
	    this.query = {};
	  }
	  if (rest) this.pathname = rest;
	  if (slashedProtocol[lowerProto] &&
	      this.hostname && !this.pathname) {
	    this.pathname = '/';
	  }

	  //to support http.request
	  if (this.pathname || this.search) {
	    var p = this.pathname || '';
	    var s = this.search || '';
	    this.path = p + s;
	  }

	  // finally, reconstruct the href based on what has been validated.
	  this.href = this.format();
	  return this;
	};

	// format a parsed object into a url string
	function urlFormat(obj) {
	  // ensure it's an object, and not a string url.
	  // If it's an obj, this is a no-op.
	  // this way, you can call url_format() on strings
	  // to clean up potentially wonky urls.
	  if (isString(obj)) obj = urlParse(obj);
	  if (!(obj instanceof Url)) return Url.prototype.format.call(obj);
	  return obj.format();
	}

	Url.prototype.format = function() {
	  var auth = this.auth || '';
	  if (auth) {
	    auth = encodeURIComponent(auth);
	    auth = auth.replace(/%3A/i, ':');
	    auth += '@';
	  }

	  var protocol = this.protocol || '',
	      pathname = this.pathname || '',
	      hash = this.hash || '',
	      host = false,
	      query = '';

	  if (this.host) {
	    host = auth + this.host;
	  } else if (this.hostname) {
	    host = auth + (this.hostname.indexOf(':') === -1 ?
	        this.hostname :
	        '[' + this.hostname + ']');
	    if (this.port) {
	      host += ':' + this.port;
	    }
	  }

	  if (this.query &&
	      isObject(this.query) &&
	      Object.keys(this.query).length) {
	    query = querystring.stringify(this.query);
	  }

	  var search = this.search || (query && ('?' + query)) || '';

	  if (protocol && protocol.substr(-1) !== ':') protocol += ':';

	  // only the slashedProtocols get the //.  Not mailto:, xmpp:, etc.
	  // unless they had them to begin with.
	  if (this.slashes ||
	      (!protocol || slashedProtocol[protocol]) && host !== false) {
	    host = '//' + (host || '');
	    if (pathname && pathname.charAt(0) !== '/') pathname = '/' + pathname;
	  } else if (!host) {
	    host = '';
	  }

	  if (hash && hash.charAt(0) !== '#') hash = '#' + hash;
	  if (search && search.charAt(0) !== '?') search = '?' + search;

	  pathname = pathname.replace(/[?#]/g, function(match) {
	    return encodeURIComponent(match);
	  });
	  search = search.replace('#', '%23');

	  return protocol + host + pathname + search + hash;
	};

	function urlResolve(source, relative) {
	  return urlParse(source, false, true).resolve(relative);
	}

	Url.prototype.resolve = function(relative) {
	  return this.resolveObject(urlParse(relative, false, true)).format();
	};

	function urlResolveObject(source, relative) {
	  if (!source) return relative;
	  return urlParse(source, false, true).resolveObject(relative);
	}

	Url.prototype.resolveObject = function(relative) {
	  if (isString(relative)) {
	    var rel = new Url();
	    rel.parse(relative, false, true);
	    relative = rel;
	  }

	  var result = new Url();
	  Object.keys(this).forEach(function(k) {
	    result[k] = this[k];
	  }, this);

	  // hash is always overridden, no matter what.
	  // even href="" will remove it.
	  result.hash = relative.hash;

	  // if the relative url is empty, then there's nothing left to do here.
	  if (relative.href === '') {
	    result.href = result.format();
	    return result;
	  }

	  // hrefs like //foo/bar always cut to the protocol.
	  if (relative.slashes && !relative.protocol) {
	    // take everything except the protocol from relative
	    Object.keys(relative).forEach(function(k) {
	      if (k !== 'protocol')
	        result[k] = relative[k];
	    });

	    //urlParse appends trailing / to urls like http://www.example.com
	    if (slashedProtocol[result.protocol] &&
	        result.hostname && !result.pathname) {
	      result.path = result.pathname = '/';
	    }

	    result.href = result.format();
	    return result;
	  }

	  if (relative.protocol && relative.protocol !== result.protocol) {
	    // if it's a known url protocol, then changing
	    // the protocol does weird things
	    // first, if it's not file:, then we MUST have a host,
	    // and if there was a path
	    // to begin with, then we MUST have a path.
	    // if it is file:, then the host is dropped,
	    // because that's known to be hostless.
	    // anything else is assumed to be absolute.
	    if (!slashedProtocol[relative.protocol]) {
	      Object.keys(relative).forEach(function(k) {
	        result[k] = relative[k];
	      });
	      result.href = result.format();
	      return result;
	    }

	    result.protocol = relative.protocol;
	    if (!relative.host && !hostlessProtocol[relative.protocol]) {
	      var relPath = (relative.pathname || '').split('/');
	      while (relPath.length && !(relative.host = relPath.shift()));
	      if (!relative.host) relative.host = '';
	      if (!relative.hostname) relative.hostname = '';
	      if (relPath[0] !== '') relPath.unshift('');
	      if (relPath.length < 2) relPath.unshift('');
	      result.pathname = relPath.join('/');
	    } else {
	      result.pathname = relative.pathname;
	    }
	    result.search = relative.search;
	    result.query = relative.query;
	    result.host = relative.host || '';
	    result.auth = relative.auth;
	    result.hostname = relative.hostname || relative.host;
	    result.port = relative.port;
	    // to support http.request
	    if (result.pathname || result.search) {
	      var p = result.pathname || '';
	      var s = result.search || '';
	      result.path = p + s;
	    }
	    result.slashes = result.slashes || relative.slashes;
	    result.href = result.format();
	    return result;
	  }

	  var isSourceAbs = (result.pathname && result.pathname.charAt(0) === '/'),
	      isRelAbs = (
	          relative.host ||
	          relative.pathname && relative.pathname.charAt(0) === '/'
	      ),
	      mustEndAbs = (isRelAbs || isSourceAbs ||
	                    (result.host && relative.pathname)),
	      removeAllDots = mustEndAbs,
	      srcPath = result.pathname && result.pathname.split('/') || [],
	      relPath = relative.pathname && relative.pathname.split('/') || [],
	      psychotic = result.protocol && !slashedProtocol[result.protocol];

	  // if the url is a non-slashed url, then relative
	  // links like ../.. should be able
	  // to crawl up to the hostname, as well.  This is strange.
	  // result.protocol has already been set by now.
	  // Later on, put the first path part into the host field.
	  if (psychotic) {
	    result.hostname = '';
	    result.port = null;
	    if (result.host) {
	      if (srcPath[0] === '') srcPath[0] = result.host;
	      else srcPath.unshift(result.host);
	    }
	    result.host = '';
	    if (relative.protocol) {
	      relative.hostname = null;
	      relative.port = null;
	      if (relative.host) {
	        if (relPath[0] === '') relPath[0] = relative.host;
	        else relPath.unshift(relative.host);
	      }
	      relative.host = null;
	    }
	    mustEndAbs = mustEndAbs && (relPath[0] === '' || srcPath[0] === '');
	  }

	  if (isRelAbs) {
	    // it's absolute.
	    result.host = (relative.host || relative.host === '') ?
	                  relative.host : result.host;
	    result.hostname = (relative.hostname || relative.hostname === '') ?
	                      relative.hostname : result.hostname;
	    result.search = relative.search;
	    result.query = relative.query;
	    srcPath = relPath;
	    // fall through to the dot-handling below.
	  } else if (relPath.length) {
	    // it's relative
	    // throw away the existing file, and take the new path instead.
	    if (!srcPath) srcPath = [];
	    srcPath.pop();
	    srcPath = srcPath.concat(relPath);
	    result.search = relative.search;
	    result.query = relative.query;
	  } else if (!isNullOrUndefined(relative.search)) {
	    // just pull out the search.
	    // like href='?foo'.
	    // Put this after the other two cases because it simplifies the booleans
	    if (psychotic) {
	      result.hostname = result.host = srcPath.shift();
	      //occationaly the auth can get stuck only in host
	      //this especialy happens in cases like
	      //url.resolveObject('mailto:local1@domain1', 'local2@domain2')
	      var authInHost = result.host && result.host.indexOf('@') > 0 ?
	                       result.host.split('@') : false;
	      if (authInHost) {
	        result.auth = authInHost.shift();
	        result.host = result.hostname = authInHost.shift();
	      }
	    }
	    result.search = relative.search;
	    result.query = relative.query;
	    //to support http.request
	    if (!isNull(result.pathname) || !isNull(result.search)) {
	      result.path = (result.pathname ? result.pathname : '') +
	                    (result.search ? result.search : '');
	    }
	    result.href = result.format();
	    return result;
	  }

	  if (!srcPath.length) {
	    // no path at all.  easy.
	    // we've already handled the other stuff above.
	    result.pathname = null;
	    //to support http.request
	    if (result.search) {
	      result.path = '/' + result.search;
	    } else {
	      result.path = null;
	    }
	    result.href = result.format();
	    return result;
	  }

	  // if a url ENDs in . or .., then it must get a trailing slash.
	  // however, if it ends in anything else non-slashy,
	  // then it must NOT get a trailing slash.
	  var last = srcPath.slice(-1)[0];
	  var hasTrailingSlash = (
	      (result.host || relative.host) && (last === '.' || last === '..') ||
	      last === '');

	  // strip single dots, resolve double dots to parent dir
	  // if the path tries to go above the root, `up` ends up > 0
	  var up = 0;
	  for (var i = srcPath.length; i >= 0; i--) {
	    last = srcPath[i];
	    if (last == '.') {
	      srcPath.splice(i, 1);
	    } else if (last === '..') {
	      srcPath.splice(i, 1);
	      up++;
	    } else if (up) {
	      srcPath.splice(i, 1);
	      up--;
	    }
	  }

	  // if the path is allowed to go above the root, restore leading ..s
	  if (!mustEndAbs && !removeAllDots) {
	    for (; up--; up) {
	      srcPath.unshift('..');
	    }
	  }

	  if (mustEndAbs && srcPath[0] !== '' &&
	      (!srcPath[0] || srcPath[0].charAt(0) !== '/')) {
	    srcPath.unshift('');
	  }

	  if (hasTrailingSlash && (srcPath.join('/').substr(-1) !== '/')) {
	    srcPath.push('');
	  }

	  var isAbsolute = srcPath[0] === '' ||
	      (srcPath[0] && srcPath[0].charAt(0) === '/');

	  // put the host back
	  if (psychotic) {
	    result.hostname = result.host = isAbsolute ? '' :
	                                    srcPath.length ? srcPath.shift() : '';
	    //occationaly the auth can get stuck only in host
	    //this especialy happens in cases like
	    //url.resolveObject('mailto:local1@domain1', 'local2@domain2')
	    var authInHost = result.host && result.host.indexOf('@') > 0 ?
	                     result.host.split('@') : false;
	    if (authInHost) {
	      result.auth = authInHost.shift();
	      result.host = result.hostname = authInHost.shift();
	    }
	  }

	  mustEndAbs = mustEndAbs || (result.host && srcPath.length);

	  if (mustEndAbs && !isAbsolute) {
	    srcPath.unshift('');
	  }

	  if (!srcPath.length) {
	    result.pathname = null;
	    result.path = null;
	  } else {
	    result.pathname = srcPath.join('/');
	  }

	  //to support request.http
	  if (!isNull(result.pathname) || !isNull(result.search)) {
	    result.path = (result.pathname ? result.pathname : '') +
	                  (result.search ? result.search : '');
	  }
	  result.auth = relative.auth || result.auth;
	  result.slashes = result.slashes || relative.slashes;
	  result.href = result.format();
	  return result;
	};

	Url.prototype.parseHost = function() {
	  var host = this.host;
	  var port = portPattern.exec(host);
	  if (port) {
	    port = port[0];
	    if (port !== ':') {
	      this.port = port.substr(1);
	    }
	    host = host.substr(0, host.length - port.length);
	  }
	  if (host) this.hostname = host;
	};

	function isString(arg) {
	  return typeof arg === "string";
	}

	function isObject(arg) {
	  return typeof arg === 'object' && arg !== null;
	}

	function isNull(arg) {
	  return arg === null;
	}
	function isNullOrUndefined(arg) {
	  return  arg == null;
	}


/***/ },
/* 3 */
/***/ function(module, exports, __webpack_require__) {

	var __WEBPACK_AMD_DEFINE_RESULT__;/* WEBPACK VAR INJECTION */(function(module, global) {/*! https://mths.be/punycode v1.3.2 by @mathias */
	;(function(root) {

		/** Detect free variables */
		var freeExports = typeof exports == 'object' && exports &&
			!exports.nodeType && exports;
		var freeModule = typeof module == 'object' && module &&
			!module.nodeType && module;
		var freeGlobal = typeof global == 'object' && global;
		if (
			freeGlobal.global === freeGlobal ||
			freeGlobal.window === freeGlobal ||
			freeGlobal.self === freeGlobal
		) {
			root = freeGlobal;
		}

		/**
		 * The `punycode` object.
		 * @name punycode
		 * @type Object
		 */
		var punycode,

		/** Highest positive signed 32-bit float value */
		maxInt = 2147483647, // aka. 0x7FFFFFFF or 2^31-1

		/** Bootstring parameters */
		base = 36,
		tMin = 1,
		tMax = 26,
		skew = 38,
		damp = 700,
		initialBias = 72,
		initialN = 128, // 0x80
		delimiter = '-', // '\x2D'

		/** Regular expressions */
		regexPunycode = /^xn--/,
		regexNonASCII = /[^\x20-\x7E]/, // unprintable ASCII chars + non-ASCII chars
		regexSeparators = /[\x2E\u3002\uFF0E\uFF61]/g, // RFC 3490 separators

		/** Error messages */
		errors = {
			'overflow': 'Overflow: input needs wider integers to process',
			'not-basic': 'Illegal input >= 0x80 (not a basic code point)',
			'invalid-input': 'Invalid input'
		},

		/** Convenience shortcuts */
		baseMinusTMin = base - tMin,
		floor = Math.floor,
		stringFromCharCode = String.fromCharCode,

		/** Temporary variable */
		key;

		/*--------------------------------------------------------------------------*/

		/**
		 * A generic error utility function.
		 * @private
		 * @param {String} type The error type.
		 * @returns {Error} Throws a `RangeError` with the applicable error message.
		 */
		function error(type) {
			throw RangeError(errors[type]);
		}

		/**
		 * A generic `Array#map` utility function.
		 * @private
		 * @param {Array} array The array to iterate over.
		 * @param {Function} callback The function that gets called for every array
		 * item.
		 * @returns {Array} A new array of values returned by the callback function.
		 */
		function map(array, fn) {
			var length = array.length;
			var result = [];
			while (length--) {
				result[length] = fn(array[length]);
			}
			return result;
		}

		/**
		 * A simple `Array#map`-like wrapper to work with domain name strings or email
		 * addresses.
		 * @private
		 * @param {String} domain The domain name or email address.
		 * @param {Function} callback The function that gets called for every
		 * character.
		 * @returns {Array} A new string of characters returned by the callback
		 * function.
		 */
		function mapDomain(string, fn) {
			var parts = string.split('@');
			var result = '';
			if (parts.length > 1) {
				// In email addresses, only the domain name should be punycoded. Leave
				// the local part (i.e. everything up to `@`) intact.
				result = parts[0] + '@';
				string = parts[1];
			}
			// Avoid `split(regex)` for IE8 compatibility. See #17.
			string = string.replace(regexSeparators, '\x2E');
			var labels = string.split('.');
			var encoded = map(labels, fn).join('.');
			return result + encoded;
		}

		/**
		 * Creates an array containing the numeric code points of each Unicode
		 * character in the string. While JavaScript uses UCS-2 internally,
		 * this function will convert a pair of surrogate halves (each of which
		 * UCS-2 exposes as separate characters) into a single code point,
		 * matching UTF-16.
		 * @see `punycode.ucs2.encode`
		 * @see <https://mathiasbynens.be/notes/javascript-encoding>
		 * @memberOf punycode.ucs2
		 * @name decode
		 * @param {String} string The Unicode input string (UCS-2).
		 * @returns {Array} The new array of code points.
		 */
		function ucs2decode(string) {
			var output = [],
			    counter = 0,
			    length = string.length,
			    value,
			    extra;
			while (counter < length) {
				value = string.charCodeAt(counter++);
				if (value >= 0xD800 && value <= 0xDBFF && counter < length) {
					// high surrogate, and there is a next character
					extra = string.charCodeAt(counter++);
					if ((extra & 0xFC00) == 0xDC00) { // low surrogate
						output.push(((value & 0x3FF) << 10) + (extra & 0x3FF) + 0x10000);
					} else {
						// unmatched surrogate; only append this code unit, in case the next
						// code unit is the high surrogate of a surrogate pair
						output.push(value);
						counter--;
					}
				} else {
					output.push(value);
				}
			}
			return output;
		}

		/**
		 * Creates a string based on an array of numeric code points.
		 * @see `punycode.ucs2.decode`
		 * @memberOf punycode.ucs2
		 * @name encode
		 * @param {Array} codePoints The array of numeric code points.
		 * @returns {String} The new Unicode string (UCS-2).
		 */
		function ucs2encode(array) {
			return map(array, function(value) {
				var output = '';
				if (value > 0xFFFF) {
					value -= 0x10000;
					output += stringFromCharCode(value >>> 10 & 0x3FF | 0xD800);
					value = 0xDC00 | value & 0x3FF;
				}
				output += stringFromCharCode(value);
				return output;
			}).join('');
		}

		/**
		 * Converts a basic code point into a digit/integer.
		 * @see `digitToBasic()`
		 * @private
		 * @param {Number} codePoint The basic numeric code point value.
		 * @returns {Number} The numeric value of a basic code point (for use in
		 * representing integers) in the range `0` to `base - 1`, or `base` if
		 * the code point does not represent a value.
		 */
		function basicToDigit(codePoint) {
			if (codePoint - 48 < 10) {
				return codePoint - 22;
			}
			if (codePoint - 65 < 26) {
				return codePoint - 65;
			}
			if (codePoint - 97 < 26) {
				return codePoint - 97;
			}
			return base;
		}

		/**
		 * Converts a digit/integer into a basic code point.
		 * @see `basicToDigit()`
		 * @private
		 * @param {Number} digit The numeric value of a basic code point.
		 * @returns {Number} The basic code point whose value (when used for
		 * representing integers) is `digit`, which needs to be in the range
		 * `0` to `base - 1`. If `flag` is non-zero, the uppercase form is
		 * used; else, the lowercase form is used. The behavior is undefined
		 * if `flag` is non-zero and `digit` has no uppercase form.
		 */
		function digitToBasic(digit, flag) {
			//  0..25 map to ASCII a..z or A..Z
			// 26..35 map to ASCII 0..9
			return digit + 22 + 75 * (digit < 26) - ((flag != 0) << 5);
		}

		/**
		 * Bias adaptation function as per section 3.4 of RFC 3492.
		 * http://tools.ietf.org/html/rfc3492#section-3.4
		 * @private
		 */
		function adapt(delta, numPoints, firstTime) {
			var k = 0;
			delta = firstTime ? floor(delta / damp) : delta >> 1;
			delta += floor(delta / numPoints);
			for (/* no initialization */; delta > baseMinusTMin * tMax >> 1; k += base) {
				delta = floor(delta / baseMinusTMin);
			}
			return floor(k + (baseMinusTMin + 1) * delta / (delta + skew));
		}

		/**
		 * Converts a Punycode string of ASCII-only symbols to a string of Unicode
		 * symbols.
		 * @memberOf punycode
		 * @param {String} input The Punycode string of ASCII-only symbols.
		 * @returns {String} The resulting string of Unicode symbols.
		 */
		function decode(input) {
			// Don't use UCS-2
			var output = [],
			    inputLength = input.length,
			    out,
			    i = 0,
			    n = initialN,
			    bias = initialBias,
			    basic,
			    j,
			    index,
			    oldi,
			    w,
			    k,
			    digit,
			    t,
			    /** Cached calculation results */
			    baseMinusT;

			// Handle the basic code points: let `basic` be the number of input code
			// points before the last delimiter, or `0` if there is none, then copy
			// the first basic code points to the output.

			basic = input.lastIndexOf(delimiter);
			if (basic < 0) {
				basic = 0;
			}

			for (j = 0; j < basic; ++j) {
				// if it's not a basic code point
				if (input.charCodeAt(j) >= 0x80) {
					error('not-basic');
				}
				output.push(input.charCodeAt(j));
			}

			// Main decoding loop: start just after the last delimiter if any basic code
			// points were copied; start at the beginning otherwise.

			for (index = basic > 0 ? basic + 1 : 0; index < inputLength; /* no final expression */) {

				// `index` is the index of the next character to be consumed.
				// Decode a generalized variable-length integer into `delta`,
				// which gets added to `i`. The overflow checking is easier
				// if we increase `i` as we go, then subtract off its starting
				// value at the end to obtain `delta`.
				for (oldi = i, w = 1, k = base; /* no condition */; k += base) {

					if (index >= inputLength) {
						error('invalid-input');
					}

					digit = basicToDigit(input.charCodeAt(index++));

					if (digit >= base || digit > floor((maxInt - i) / w)) {
						error('overflow');
					}

					i += digit * w;
					t = k <= bias ? tMin : (k >= bias + tMax ? tMax : k - bias);

					if (digit < t) {
						break;
					}

					baseMinusT = base - t;
					if (w > floor(maxInt / baseMinusT)) {
						error('overflow');
					}

					w *= baseMinusT;

				}

				out = output.length + 1;
				bias = adapt(i - oldi, out, oldi == 0);

				// `i` was supposed to wrap around from `out` to `0`,
				// incrementing `n` each time, so we'll fix that now:
				if (floor(i / out) > maxInt - n) {
					error('overflow');
				}

				n += floor(i / out);
				i %= out;

				// Insert `n` at position `i` of the output
				output.splice(i++, 0, n);

			}

			return ucs2encode(output);
		}

		/**
		 * Converts a string of Unicode symbols (e.g. a domain name label) to a
		 * Punycode string of ASCII-only symbols.
		 * @memberOf punycode
		 * @param {String} input The string of Unicode symbols.
		 * @returns {String} The resulting Punycode string of ASCII-only symbols.
		 */
		function encode(input) {
			var n,
			    delta,
			    handledCPCount,
			    basicLength,
			    bias,
			    j,
			    m,
			    q,
			    k,
			    t,
			    currentValue,
			    output = [],
			    /** `inputLength` will hold the number of code points in `input`. */
			    inputLength,
			    /** Cached calculation results */
			    handledCPCountPlusOne,
			    baseMinusT,
			    qMinusT;

			// Convert the input in UCS-2 to Unicode
			input = ucs2decode(input);

			// Cache the length
			inputLength = input.length;

			// Initialize the state
			n = initialN;
			delta = 0;
			bias = initialBias;

			// Handle the basic code points
			for (j = 0; j < inputLength; ++j) {
				currentValue = input[j];
				if (currentValue < 0x80) {
					output.push(stringFromCharCode(currentValue));
				}
			}

			handledCPCount = basicLength = output.length;

			// `handledCPCount` is the number of code points that have been handled;
			// `basicLength` is the number of basic code points.

			// Finish the basic string - if it is not empty - with a delimiter
			if (basicLength) {
				output.push(delimiter);
			}

			// Main encoding loop:
			while (handledCPCount < inputLength) {

				// All non-basic code points < n have been handled already. Find the next
				// larger one:
				for (m = maxInt, j = 0; j < inputLength; ++j) {
					currentValue = input[j];
					if (currentValue >= n && currentValue < m) {
						m = currentValue;
					}
				}

				// Increase `delta` enough to advance the decoder's <n,i> state to <m,0>,
				// but guard against overflow
				handledCPCountPlusOne = handledCPCount + 1;
				if (m - n > floor((maxInt - delta) / handledCPCountPlusOne)) {
					error('overflow');
				}

				delta += (m - n) * handledCPCountPlusOne;
				n = m;

				for (j = 0; j < inputLength; ++j) {
					currentValue = input[j];

					if (currentValue < n && ++delta > maxInt) {
						error('overflow');
					}

					if (currentValue == n) {
						// Represent delta as a generalized variable-length integer
						for (q = delta, k = base; /* no condition */; k += base) {
							t = k <= bias ? tMin : (k >= bias + tMax ? tMax : k - bias);
							if (q < t) {
								break;
							}
							qMinusT = q - t;
							baseMinusT = base - t;
							output.push(
								stringFromCharCode(digitToBasic(t + qMinusT % baseMinusT, 0))
							);
							q = floor(qMinusT / baseMinusT);
						}

						output.push(stringFromCharCode(digitToBasic(q, 0)));
						bias = adapt(delta, handledCPCountPlusOne, handledCPCount == basicLength);
						delta = 0;
						++handledCPCount;
					}
				}

				++delta;
				++n;

			}
			return output.join('');
		}

		/**
		 * Converts a Punycode string representing a domain name or an email address
		 * to Unicode. Only the Punycoded parts of the input will be converted, i.e.
		 * it doesn't matter if you call it on a string that has already been
		 * converted to Unicode.
		 * @memberOf punycode
		 * @param {String} input The Punycoded domain name or email address to
		 * convert to Unicode.
		 * @returns {String} The Unicode representation of the given Punycode
		 * string.
		 */
		function toUnicode(input) {
			return mapDomain(input, function(string) {
				return regexPunycode.test(string)
					? decode(string.slice(4).toLowerCase())
					: string;
			});
		}

		/**
		 * Converts a Unicode string representing a domain name or an email address to
		 * Punycode. Only the non-ASCII parts of the domain name will be converted,
		 * i.e. it doesn't matter if you call it with a domain that's already in
		 * ASCII.
		 * @memberOf punycode
		 * @param {String} input The domain name or email address to convert, as a
		 * Unicode string.
		 * @returns {String} The Punycode representation of the given domain name or
		 * email address.
		 */
		function toASCII(input) {
			return mapDomain(input, function(string) {
				return regexNonASCII.test(string)
					? 'xn--' + encode(string)
					: string;
			});
		}

		/*--------------------------------------------------------------------------*/

		/** Define the public API */
		punycode = {
			/**
			 * A string representing the current Punycode.js version number.
			 * @memberOf punycode
			 * @type String
			 */
			'version': '1.3.2',
			/**
			 * An object of methods to convert from JavaScript's internal character
			 * representation (UCS-2) to Unicode code points, and back.
			 * @see <https://mathiasbynens.be/notes/javascript-encoding>
			 * @memberOf punycode
			 * @type Object
			 */
			'ucs2': {
				'decode': ucs2decode,
				'encode': ucs2encode
			},
			'decode': decode,
			'encode': encode,
			'toASCII': toASCII,
			'toUnicode': toUnicode
		};

		/** Expose `punycode` */
		// Some AMD build optimizers, like r.js, check for specific condition patterns
		// like the following:
		if (
			true
		) {
			!(__WEBPACK_AMD_DEFINE_RESULT__ = function() {
				return punycode;
			}.call(exports, __webpack_require__, exports, module), __WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
		} else if (freeExports && freeModule) {
			if (module.exports == freeExports) { // in Node.js or RingoJS v0.8.0+
				freeModule.exports = punycode;
			} else { // in Narwhal or RingoJS v0.7.0-
				for (key in punycode) {
					punycode.hasOwnProperty(key) && (freeExports[key] = punycode[key]);
				}
			}
		} else { // in Rhino or a web browser
			root.punycode = punycode;
		}

	}(this));

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(4)(module), (function() { return this; }())))

/***/ },
/* 4 */
/***/ function(module, exports) {

	module.exports = function(module) {
		if(!module.webpackPolyfill) {
			module.deprecate = function() {};
			module.paths = [];
			// module.parent = undefined by default
			module.children = [];
			module.webpackPolyfill = 1;
		}
		return module;
	}


/***/ },
/* 5 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	exports.decode = exports.parse = __webpack_require__(6);
	exports.encode = exports.stringify = __webpack_require__(7);


/***/ },
/* 6 */
/***/ function(module, exports) {

	// Copyright Joyent, Inc. and other Node contributors.
	//
	// Permission is hereby granted, free of charge, to any person obtaining a
	// copy of this software and associated documentation files (the
	// "Software"), to deal in the Software without restriction, including
	// without limitation the rights to use, copy, modify, merge, publish,
	// distribute, sublicense, and/or sell copies of the Software, and to permit
	// persons to whom the Software is furnished to do so, subject to the
	// following conditions:
	//
	// The above copyright notice and this permission notice shall be included
	// in all copies or substantial portions of the Software.
	//
	// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
	// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
	// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
	// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
	// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
	// USE OR OTHER DEALINGS IN THE SOFTWARE.

	'use strict';

	// If obj.hasOwnProperty has been overridden, then calling
	// obj.hasOwnProperty(prop) will break.
	// See: https://github.com/joyent/node/issues/1707
	function hasOwnProperty(obj, prop) {
	  return Object.prototype.hasOwnProperty.call(obj, prop);
	}

	module.exports = function(qs, sep, eq, options) {
	  sep = sep || '&';
	  eq = eq || '=';
	  var obj = {};

	  if (typeof qs !== 'string' || qs.length === 0) {
	    return obj;
	  }

	  var regexp = /\+/g;
	  qs = qs.split(sep);

	  var maxKeys = 1000;
	  if (options && typeof options.maxKeys === 'number') {
	    maxKeys = options.maxKeys;
	  }

	  var len = qs.length;
	  // maxKeys <= 0 means that we should not limit keys count
	  if (maxKeys > 0 && len > maxKeys) {
	    len = maxKeys;
	  }

	  for (var i = 0; i < len; ++i) {
	    var x = qs[i].replace(regexp, '%20'),
	        idx = x.indexOf(eq),
	        kstr, vstr, k, v;

	    if (idx >= 0) {
	      kstr = x.substr(0, idx);
	      vstr = x.substr(idx + 1);
	    } else {
	      kstr = x;
	      vstr = '';
	    }

	    k = decodeURIComponent(kstr);
	    v = decodeURIComponent(vstr);

	    if (!hasOwnProperty(obj, k)) {
	      obj[k] = v;
	    } else if (Array.isArray(obj[k])) {
	      obj[k].push(v);
	    } else {
	      obj[k] = [obj[k], v];
	    }
	  }

	  return obj;
	};


/***/ },
/* 7 */
/***/ function(module, exports) {

	// Copyright Joyent, Inc. and other Node contributors.
	//
	// Permission is hereby granted, free of charge, to any person obtaining a
	// copy of this software and associated documentation files (the
	// "Software"), to deal in the Software without restriction, including
	// without limitation the rights to use, copy, modify, merge, publish,
	// distribute, sublicense, and/or sell copies of the Software, and to permit
	// persons to whom the Software is furnished to do so, subject to the
	// following conditions:
	//
	// The above copyright notice and this permission notice shall be included
	// in all copies or substantial portions of the Software.
	//
	// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
	// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
	// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
	// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
	// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
	// USE OR OTHER DEALINGS IN THE SOFTWARE.

	'use strict';

	var stringifyPrimitive = function(v) {
	  switch (typeof v) {
	    case 'string':
	      return v;

	    case 'boolean':
	      return v ? 'true' : 'false';

	    case 'number':
	      return isFinite(v) ? v : '';

	    default:
	      return '';
	  }
	};

	module.exports = function(obj, sep, eq, name) {
	  sep = sep || '&';
	  eq = eq || '=';
	  if (obj === null) {
	    obj = undefined;
	  }

	  if (typeof obj === 'object') {
	    return Object.keys(obj).map(function(k) {
	      var ks = encodeURIComponent(stringifyPrimitive(k)) + eq;
	      if (Array.isArray(obj[k])) {
	        return obj[k].map(function(v) {
	          return ks + encodeURIComponent(stringifyPrimitive(v));
	        }).join(sep);
	      } else {
	        return ks + encodeURIComponent(stringifyPrimitive(obj[k]));
	      }
	    }).join(sep);

	  }

	  if (!name) return '';
	  return encodeURIComponent(stringifyPrimitive(name)) + eq +
	         encodeURIComponent(stringifyPrimitive(obj));
	};


/***/ },
/* 8 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var transportList = __webpack_require__(9);

	module.exports = __webpack_require__(55)(transportList);

	// TODO can't get rid of this until all servers do
	if ('_sockjs_onload' in global) {
	  setTimeout(global._sockjs_onload, 1);
	}

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 9 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	module.exports = [
	  // streaming transports
	  __webpack_require__(10)
	, __webpack_require__(26)
	, __webpack_require__(36)
	, __webpack_require__(38)
	, __webpack_require__(41)(__webpack_require__(38))

	  // polling transports
	, __webpack_require__(48)
	, __webpack_require__(41)(__webpack_require__(48))
	, __webpack_require__(50)
	, __webpack_require__(51)
	, __webpack_require__(41)(__webpack_require__(50))
	, __webpack_require__(52)
	];


/***/ },
/* 10 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var utils = __webpack_require__(12)
	  , urlUtils = __webpack_require__(15)
	  , inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  , WebsocketDriver = __webpack_require__(25)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:websocket');
	}

	function WebSocketTransport(transUrl, ignore, options) {
	  if (!WebSocketTransport.enabled()) {
	    throw new Error('Transport created when disabled');
	  }

	  EventEmitter.call(this);
	  debug('constructor', transUrl);

	  var self = this;
	  var url = urlUtils.addPath(transUrl, '/websocket');
	  if (url.slice(0, 5) === 'https') {
	    url = 'wss' + url.slice(5);
	  } else {
	    url = 'ws' + url.slice(4);
	  }
	  this.url = url;

	  this.ws = new WebsocketDriver(this.url, [], options);
	  this.ws.onmessage = function(e) {
	    debug('message event', e.data);
	    self.emit('message', e.data);
	  };
	  // Firefox has an interesting bug. If a websocket connection is
	  // created after onunload, it stays alive even when user
	  // navigates away from the page. In such situation let's lie -
	  // let's not open the ws connection at all. See:
	  // https://github.com/sockjs/sockjs-client/issues/28
	  // https://bugzilla.mozilla.org/show_bug.cgi?id=696085
	  this.unloadRef = utils.unloadAdd(function() {
	    debug('unload');
	    self.ws.close();
	  });
	  this.ws.onclose = function(e) {
	    debug('close event', e.code, e.reason);
	    self.emit('close', e.code, e.reason);
	    self._cleanup();
	  };
	  this.ws.onerror = function(e) {
	    debug('error event', e);
	    self.emit('close', 1006, 'WebSocket connection broken');
	    self._cleanup();
	  };
	}

	inherits(WebSocketTransport, EventEmitter);

	WebSocketTransport.prototype.send = function(data) {
	  var msg = '[' + data + ']';
	  debug('send', msg);
	  this.ws.send(msg);
	};

	WebSocketTransport.prototype.close = function() {
	  debug('close');
	  var ws = this.ws;
	  this._cleanup();
	  if (ws) {
	    ws.close();
	  }
	};

	WebSocketTransport.prototype._cleanup = function() {
	  debug('_cleanup');
	  var ws = this.ws;
	  if (ws) {
	    ws.onmessage = ws.onclose = ws.onerror = null;
	  }
	  utils.unloadDel(this.unloadRef);
	  this.unloadRef = this.ws = null;
	  this.removeAllListeners();
	};

	WebSocketTransport.enabled = function() {
	  debug('enabled');
	  return !!WebsocketDriver;
	};
	WebSocketTransport.transportName = 'websocket';

	// In theory, ws should require 1 round trip. But in chrome, this is
	// not very stable over SSL. Most likely a ws connection requires a
	// separate SSL connection, in which case 2 round trips are an
	// absolute minumum.
	WebSocketTransport.roundTrips = 2;

	module.exports = WebSocketTransport;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 11 */
/***/ function(module, exports) {

	// shim for using process in browser
	var process = module.exports = {};

	// cached from whatever global is present so that test runners that stub it
	// don't break things.  But we need to wrap it in a try catch in case it is
	// wrapped in strict mode code which doesn't define any globals.  It's inside a
	// function because try/catches deoptimize in certain engines.

	var cachedSetTimeout;
	var cachedClearTimeout;

	function defaultSetTimout() {
	    throw new Error('setTimeout has not been defined');
	}
	function defaultClearTimeout () {
	    throw new Error('clearTimeout has not been defined');
	}
	(function () {
	    try {
	        if (typeof setTimeout === 'function') {
	            cachedSetTimeout = setTimeout;
	        } else {
	            cachedSetTimeout = defaultSetTimout;
	        }
	    } catch (e) {
	        cachedSetTimeout = defaultSetTimout;
	    }
	    try {
	        if (typeof clearTimeout === 'function') {
	            cachedClearTimeout = clearTimeout;
	        } else {
	            cachedClearTimeout = defaultClearTimeout;
	        }
	    } catch (e) {
	        cachedClearTimeout = defaultClearTimeout;
	    }
	} ())
	function runTimeout(fun) {
	    if (cachedSetTimeout === setTimeout) {
	        //normal enviroments in sane situations
	        return setTimeout(fun, 0);
	    }
	    // if setTimeout wasn't available but was latter defined
	    if ((cachedSetTimeout === defaultSetTimout || !cachedSetTimeout) && setTimeout) {
	        cachedSetTimeout = setTimeout;
	        return setTimeout(fun, 0);
	    }
	    try {
	        // when when somebody has screwed with setTimeout but no I.E. maddness
	        return cachedSetTimeout(fun, 0);
	    } catch(e){
	        try {
	            // When we are in I.E. but the script has been evaled so I.E. doesn't trust the global object when called normally
	            return cachedSetTimeout.call(null, fun, 0);
	        } catch(e){
	            // same as above but when it's a version of I.E. that must have the global object for 'this', hopfully our context correct otherwise it will throw a global error
	            return cachedSetTimeout.call(this, fun, 0);
	        }
	    }


	}
	function runClearTimeout(marker) {
	    if (cachedClearTimeout === clearTimeout) {
	        //normal enviroments in sane situations
	        return clearTimeout(marker);
	    }
	    // if clearTimeout wasn't available but was latter defined
	    if ((cachedClearTimeout === defaultClearTimeout || !cachedClearTimeout) && clearTimeout) {
	        cachedClearTimeout = clearTimeout;
	        return clearTimeout(marker);
	    }
	    try {
	        // when when somebody has screwed with setTimeout but no I.E. maddness
	        return cachedClearTimeout(marker);
	    } catch (e){
	        try {
	            // When we are in I.E. but the script has been evaled so I.E. doesn't  trust the global object when called normally
	            return cachedClearTimeout.call(null, marker);
	        } catch (e){
	            // same as above but when it's a version of I.E. that must have the global object for 'this', hopfully our context correct otherwise it will throw a global error.
	            // Some versions of I.E. have different rules for clearTimeout vs setTimeout
	            return cachedClearTimeout.call(this, marker);
	        }
	    }



	}
	var queue = [];
	var draining = false;
	var currentQueue;
	var queueIndex = -1;

	function cleanUpNextTick() {
	    if (!draining || !currentQueue) {
	        return;
	    }
	    draining = false;
	    if (currentQueue.length) {
	        queue = currentQueue.concat(queue);
	    } else {
	        queueIndex = -1;
	    }
	    if (queue.length) {
	        drainQueue();
	    }
	}

	function drainQueue() {
	    if (draining) {
	        return;
	    }
	    var timeout = runTimeout(cleanUpNextTick);
	    draining = true;

	    var len = queue.length;
	    while(len) {
	        currentQueue = queue;
	        queue = [];
	        while (++queueIndex < len) {
	            if (currentQueue) {
	                currentQueue[queueIndex].run();
	            }
	        }
	        queueIndex = -1;
	        len = queue.length;
	    }
	    currentQueue = null;
	    draining = false;
	    runClearTimeout(timeout);
	}

	process.nextTick = function (fun) {
	    var args = new Array(arguments.length - 1);
	    if (arguments.length > 1) {
	        for (var i = 1; i < arguments.length; i++) {
	            args[i - 1] = arguments[i];
	        }
	    }
	    queue.push(new Item(fun, args));
	    if (queue.length === 1 && !draining) {
	        runTimeout(drainQueue);
	    }
	};

	// v8 likes predictible objects
	function Item(fun, array) {
	    this.fun = fun;
	    this.array = array;
	}
	Item.prototype.run = function () {
	    this.fun.apply(null, this.array);
	};
	process.title = 'browser';
	process.browser = true;
	process.env = {};
	process.argv = [];
	process.version = ''; // empty string to avoid regexp issues
	process.versions = {};

	function noop() {}

	process.on = noop;
	process.addListener = noop;
	process.once = noop;
	process.off = noop;
	process.removeListener = noop;
	process.removeAllListeners = noop;
	process.emit = noop;
	process.prependListener = noop;
	process.prependOnceListener = noop;

	process.listeners = function (name) { return [] }

	process.binding = function (name) {
	    throw new Error('process.binding is not supported');
	};

	process.cwd = function () { return '/' };
	process.chdir = function (dir) {
	    throw new Error('process.chdir is not supported');
	};
	process.umask = function() { return 0; };


/***/ },
/* 12 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var random = __webpack_require__(13);

	var onUnload = {}
	  , afterUnload = false
	    // detect google chrome packaged apps because they don't allow the 'unload' event
	  , isChromePackagedApp = global.chrome && global.chrome.app && global.chrome.app.runtime
	  ;

	module.exports = {
	  attachEvent: function(event, listener) {
	    if (typeof global.addEventListener !== 'undefined') {
	      global.addEventListener(event, listener, false);
	    } else if (global.document && global.attachEvent) {
	      // IE quirks.
	      // According to: http://stevesouders.com/misc/test-postmessage.php
	      // the message gets delivered only to 'document', not 'window'.
	      global.document.attachEvent('on' + event, listener);
	      // I get 'window' for ie8.
	      global.attachEvent('on' + event, listener);
	    }
	  }

	, detachEvent: function(event, listener) {
	    if (typeof global.addEventListener !== 'undefined') {
	      global.removeEventListener(event, listener, false);
	    } else if (global.document && global.detachEvent) {
	      global.document.detachEvent('on' + event, listener);
	      global.detachEvent('on' + event, listener);
	    }
	  }

	, unloadAdd: function(listener) {
	    if (isChromePackagedApp) {
	      return null;
	    }

	    var ref = random.string(8);
	    onUnload[ref] = listener;
	    if (afterUnload) {
	      setTimeout(this.triggerUnloadCallbacks, 0);
	    }
	    return ref;
	  }

	, unloadDel: function(ref) {
	    if (ref in onUnload) {
	      delete onUnload[ref];
	    }
	  }

	, triggerUnloadCallbacks: function() {
	    for (var ref in onUnload) {
	      onUnload[ref]();
	      delete onUnload[ref];
	    }
	  }
	};

	var unloadTriggered = function() {
	  if (afterUnload) {
	    return;
	  }
	  afterUnload = true;
	  module.exports.triggerUnloadCallbacks();
	};

	// 'unload' alone is not reliable in opera within an iframe, but we
	// can't use `beforeunload` as IE fires it on javascript: links.
	if (!isChromePackagedApp) {
	  module.exports.attachEvent('unload', unloadTriggered);
	}

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 13 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	/* global crypto:true */
	var crypto = __webpack_require__(14);

	// This string has length 32, a power of 2, so the modulus doesn't introduce a
	// bias.
	var _randomStringChars = 'abcdefghijklmnopqrstuvwxyz012345';
	module.exports = {
	  string: function(length) {
	    var max = _randomStringChars.length;
	    var bytes = crypto.randomBytes(length);
	    var ret = [];
	    for (var i = 0; i < length; i++) {
	      ret.push(_randomStringChars.substr(bytes[i] % max, 1));
	    }
	    return ret.join('');
	  }

	, number: function(max) {
	    return Math.floor(Math.random() * max);
	  }

	, numberString: function(max) {
	    var t = ('' + (max - 1)).length;
	    var p = new Array(t + 1).join('0');
	    return (p + this.number(max)).slice(-t);
	  }
	};


/***/ },
/* 14 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	if (global.crypto && global.crypto.getRandomValues) {
	  module.exports.randomBytes = function(length) {
	    var bytes = new Uint8Array(length);
	    global.crypto.getRandomValues(bytes);
	    return bytes;
	  };
	} else {
	  module.exports.randomBytes = function(length) {
	    var bytes = new Array(length);
	    for (var i = 0; i < length; i++) {
	      bytes[i] = Math.floor(Math.random() * 256);
	    }
	    return bytes;
	  };
	}

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 15 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var URL = __webpack_require__(16);

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:utils:url');
	}

	module.exports = {
	  getOrigin: function(url) {
	    if (!url) {
	      return null;
	    }

	    var p = new URL(url);
	    if (p.protocol === 'file:') {
	      return null;
	    }

	    var port = p.port;
	    if (!port) {
	      port = (p.protocol === 'https:') ? '443' : '80';
	    }

	    return p.protocol + '//' + p.hostname + ':' + port;
	  }

	, isOriginEqual: function(a, b) {
	    var res = this.getOrigin(a) === this.getOrigin(b);
	    debug('same', a, b, res);
	    return res;
	  }

	, isSchemeEqual: function(a, b) {
	    return (a.split(':')[0] === b.split(':')[0]);
	  }

	, addPath: function (url, path) {
	    var qs = url.split('?');
	    return qs[0] + path + (qs[1] ? '?' + qs[1] : '');
	  }

	, addQuery: function (url, q) {
	    return url + (url.indexOf('?') === -1 ? ('?' + q) : ('&' + q));
	  }
	};

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 16 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var required = __webpack_require__(17)
	  , qs = __webpack_require__(18)
	  , protocolre = /^([a-z][a-z0-9.+-]*:)?(\/\/)?([\S\s]*)/i
	  , slashes = /^[A-Za-z][A-Za-z0-9+-.]*:\/\//;

	/**
	 * These are the parse rules for the URL parser, it informs the parser
	 * about:
	 *
	 * 0. The char it Needs to parse, if it's a string it should be done using
	 *    indexOf, RegExp using exec and NaN means set as current value.
	 * 1. The property we should set when parsing this value.
	 * 2. Indication if it's backwards or forward parsing, when set as number it's
	 *    the value of extra chars that should be split off.
	 * 3. Inherit from location if non existing in the parser.
	 * 4. `toLowerCase` the resulting value.
	 */
	var rules = [
	  ['#', 'hash'],                        // Extract from the back.
	  ['?', 'query'],                       // Extract from the back.
	  ['/', 'pathname'],                    // Extract from the back.
	  ['@', 'auth', 1],                     // Extract from the front.
	  [NaN, 'host', undefined, 1, 1],       // Set left over value.
	  [/:(\d+)$/, 'port', undefined, 1],    // RegExp the back.
	  [NaN, 'hostname', undefined, 1, 1]    // Set left over.
	];

	/**
	 * These properties should not be copied or inherited from. This is only needed
	 * for all non blob URL's as a blob URL does not include a hash, only the
	 * origin.
	 *
	 * @type {Object}
	 * @private
	 */
	var ignore = { hash: 1, query: 1 };

	/**
	 * The location object differs when your code is loaded through a normal page,
	 * Worker or through a worker using a blob. And with the blobble begins the
	 * trouble as the location object will contain the URL of the blob, not the
	 * location of the page where our code is loaded in. The actual origin is
	 * encoded in the `pathname` so we can thankfully generate a good "default"
	 * location from it so we can generate proper relative URL's again.
	 *
	 * @param {Object|String} loc Optional default location object.
	 * @returns {Object} lolcation object.
	 * @api public
	 */
	function lolcation(loc) {
	  var location = global && global.location || {};
	  loc = loc || location;

	  var finaldestination = {}
	    , type = typeof loc
	    , key;

	  if ('blob:' === loc.protocol) {
	    finaldestination = new URL(unescape(loc.pathname), {});
	  } else if ('string' === type) {
	    finaldestination = new URL(loc, {});
	    for (key in ignore) delete finaldestination[key];
	  } else if ('object' === type) {
	    for (key in loc) {
	      if (key in ignore) continue;
	      finaldestination[key] = loc[key];
	    }

	    if (finaldestination.slashes === undefined) {
	      finaldestination.slashes = slashes.test(loc.href);
	    }
	  }

	  return finaldestination;
	}

	/**
	 * @typedef ProtocolExtract
	 * @type Object
	 * @property {String} protocol Protocol matched in the URL, in lowercase.
	 * @property {Boolean} slashes `true` if protocol is followed by "//", else `false`.
	 * @property {String} rest Rest of the URL that is not part of the protocol.
	 */

	/**
	 * Extract protocol information from a URL with/without double slash ("//").
	 *
	 * @param {String} address URL we want to extract from.
	 * @return {ProtocolExtract} Extracted information.
	 * @api private
	 */
	function extractProtocol(address) {
	  var match = protocolre.exec(address);

	  return {
	    protocol: match[1] ? match[1].toLowerCase() : '',
	    slashes: !!match[2],
	    rest: match[3]
	  };
	}

	/**
	 * Resolve a relative URL pathname against a base URL pathname.
	 *
	 * @param {String} relative Pathname of the relative URL.
	 * @param {String} base Pathname of the base URL.
	 * @return {String} Resolved pathname.
	 * @api private
	 */
	function resolve(relative, base) {
	  var path = (base || '/').split('/').slice(0, -1).concat(relative.split('/'))
	    , i = path.length
	    , last = path[i - 1]
	    , unshift = false
	    , up = 0;

	  while (i--) {
	    if (path[i] === '.') {
	      path.splice(i, 1);
	    } else if (path[i] === '..') {
	      path.splice(i, 1);
	      up++;
	    } else if (up) {
	      if (i === 0) unshift = true;
	      path.splice(i, 1);
	      up--;
	    }
	  }

	  if (unshift) path.unshift('');
	  if (last === '.' || last === '..') path.push('');

	  return path.join('/');
	}

	/**
	 * The actual URL instance. Instead of returning an object we've opted-in to
	 * create an actual constructor as it's much more memory efficient and
	 * faster and it pleases my OCD.
	 *
	 * @constructor
	 * @param {String} address URL we want to parse.
	 * @param {Object|String} location Location defaults for relative paths.
	 * @param {Boolean|Function} parser Parser for the query string.
	 * @api public
	 */
	function URL(address, location, parser) {
	  if (!(this instanceof URL)) {
	    return new URL(address, location, parser);
	  }

	  var relative, extracted, parse, instruction, index, key
	    , instructions = rules.slice()
	    , type = typeof location
	    , url = this
	    , i = 0;

	  //
	  // The following if statements allows this module two have compatibility with
	  // 2 different API:
	  //
	  // 1. Node.js's `url.parse` api which accepts a URL, boolean as arguments
	  //    where the boolean indicates that the query string should also be parsed.
	  //
	  // 2. The `URL` interface of the browser which accepts a URL, object as
	  //    arguments. The supplied object will be used as default values / fall-back
	  //    for relative paths.
	  //
	  if ('object' !== type && 'string' !== type) {
	    parser = location;
	    location = null;
	  }

	  if (parser && 'function' !== typeof parser) parser = qs.parse;

	  location = lolcation(location);

	  //
	  // Extract protocol information before running the instructions.
	  //
	  extracted = extractProtocol(address || '');
	  relative = !extracted.protocol && !extracted.slashes;
	  url.slashes = extracted.slashes || relative && location.slashes;
	  url.protocol = extracted.protocol || location.protocol || '';
	  address = extracted.rest;

	  //
	  // When the authority component is absent the URL starts with a path
	  // component.
	  //
	  if (!extracted.slashes) instructions[2] = [/(.*)/, 'pathname'];

	  for (; i < instructions.length; i++) {
	    instruction = instructions[i];
	    parse = instruction[0];
	    key = instruction[1];

	    if (parse !== parse) {
	      url[key] = address;
	    } else if ('string' === typeof parse) {
	      if (~(index = address.indexOf(parse))) {
	        if ('number' === typeof instruction[2]) {
	          url[key] = address.slice(0, index);
	          address = address.slice(index + instruction[2]);
	        } else {
	          url[key] = address.slice(index);
	          address = address.slice(0, index);
	        }
	      }
	    } else if ((index = parse.exec(address))) {
	      url[key] = index[1];
	      address = address.slice(0, index.index);
	    }

	    url[key] = url[key] || (
	      relative && instruction[3] ? location[key] || '' : ''
	    );

	    //
	    // Hostname, host and protocol should be lowercased so they can be used to
	    // create a proper `origin`.
	    //
	    if (instruction[4]) url[key] = url[key].toLowerCase();
	  }

	  //
	  // Also parse the supplied query string in to an object. If we're supplied
	  // with a custom parser as function use that instead of the default build-in
	  // parser.
	  //
	  if (parser) url.query = parser(url.query);

	  //
	  // If the URL is relative, resolve the pathname against the base URL.
	  //
	  if (
	      relative
	    && location.slashes
	    && url.pathname.charAt(0) !== '/'
	    && (url.pathname !== '' || location.pathname !== '')
	  ) {
	    url.pathname = resolve(url.pathname, location.pathname);
	  }

	  //
	  // We should not add port numbers if they are already the default port number
	  // for a given protocol. As the host also contains the port number we're going
	  // override it with the hostname which contains no port number.
	  //
	  if (!required(url.port, url.protocol)) {
	    url.host = url.hostname;
	    url.port = '';
	  }

	  //
	  // Parse down the `auth` for the username and password.
	  //
	  url.username = url.password = '';
	  if (url.auth) {
	    instruction = url.auth.split(':');
	    url.username = instruction[0] || '';
	    url.password = instruction[1] || '';
	  }

	  url.origin = url.protocol && url.host && url.protocol !== 'file:'
	    ? url.protocol +'//'+ url.host
	    : 'null';

	  //
	  // The href is just the compiled result.
	  //
	  url.href = url.toString();
	}

	/**
	 * This is convenience method for changing properties in the URL instance to
	 * insure that they all propagate correctly.
	 *
	 * @param {String} part          Property we need to adjust.
	 * @param {Mixed} value          The newly assigned value.
	 * @param {Boolean|Function} fn  When setting the query, it will be the function
	 *                               used to parse the query.
	 *                               When setting the protocol, double slash will be
	 *                               removed from the final url if it is true.
	 * @returns {URL}
	 * @api public
	 */
	function set(part, value, fn) {
	  var url = this;

	  switch (part) {
	    case 'query':
	      if ('string' === typeof value && value.length) {
	        value = (fn || qs.parse)(value);
	      }

	      url[part] = value;
	      break;

	    case 'port':
	      url[part] = value;

	      if (!required(value, url.protocol)) {
	        url.host = url.hostname;
	        url[part] = '';
	      } else if (value) {
	        url.host = url.hostname +':'+ value;
	      }

	      break;

	    case 'hostname':
	      url[part] = value;

	      if (url.port) value += ':'+ url.port;
	      url.host = value;
	      break;

	    case 'host':
	      url[part] = value;

	      if (/:\d+$/.test(value)) {
	        value = value.split(':');
	        url.port = value.pop();
	        url.hostname = value.join(':');
	      } else {
	        url.hostname = value;
	        url.port = '';
	      }

	      break;

	    case 'protocol':
	      url.protocol = value.toLowerCase();
	      url.slashes = !fn;
	      break;

	    case 'pathname':
	    case 'hash':
	      if (value) {
	        var char = part === 'pathname' ? '/' : '#';
	        url[part] = value.charAt(0) !== char ? char + value : value;
	      } else {
	        url[part] = value;
	      }
	      break;

	    default:
	      url[part] = value;
	  }

	  for (var i = 0; i < rules.length; i++) {
	    var ins = rules[i];

	    if (ins[4]) url[ins[1]] = url[ins[1]].toLowerCase();
	  }

	  url.origin = url.protocol && url.host && url.protocol !== 'file:'
	    ? url.protocol +'//'+ url.host
	    : 'null';

	  url.href = url.toString();

	  return url;
	}

	/**
	 * Transform the properties back in to a valid and full URL string.
	 *
	 * @param {Function} stringify Optional query stringify function.
	 * @returns {String}
	 * @api public
	 */
	function toString(stringify) {
	  if (!stringify || 'function' !== typeof stringify) stringify = qs.stringify;

	  var query
	    , url = this
	    , protocol = url.protocol;

	  if (protocol && protocol.charAt(protocol.length - 1) !== ':') protocol += ':';

	  var result = protocol + (url.slashes ? '//' : '');

	  if (url.username) {
	    result += url.username;
	    if (url.password) result += ':'+ url.password;
	    result += '@';
	  }

	  result += url.host + url.pathname;

	  query = 'object' === typeof url.query ? stringify(url.query) : url.query;
	  if (query) result += '?' !== query.charAt(0) ? '?'+ query : query;

	  if (url.hash) result += url.hash;

	  return result;
	}

	URL.prototype = { set: set, toString: toString };

	//
	// Expose the URL parser and some additional properties that might be useful for
	// others or testing.
	//
	URL.extractProtocol = extractProtocol;
	URL.location = lolcation;
	URL.qs = qs;

	module.exports = URL;

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 17 */
/***/ function(module, exports) {

	'use strict';

	/**
	 * Check if we're required to add a port number.
	 *
	 * @see https://url.spec.whatwg.org/#default-port
	 * @param {Number|String} port Port number we need to check
	 * @param {String} protocol Protocol we need to check against.
	 * @returns {Boolean} Is it a default port for the given protocol
	 * @api private
	 */
	module.exports = function required(port, protocol) {
	  protocol = protocol.split(':')[0];
	  port = +port;

	  if (!port) return false;

	  switch (protocol) {
	    case 'http':
	    case 'ws':
	    return port !== 80;

	    case 'https':
	    case 'wss':
	    return port !== 443;

	    case 'ftp':
	    return port !== 21;

	    case 'gopher':
	    return port !== 70;

	    case 'file':
	    return false;
	  }

	  return port !== 0;
	};


/***/ },
/* 18 */
/***/ function(module, exports) {

	'use strict';

	var has = Object.prototype.hasOwnProperty;

	/**
	 * Decode a URI encoded string.
	 *
	 * @param {String} input The URI encoded string.
	 * @returns {String} The decoded string.
	 * @api private
	 */
	function decode(input) {
	  return decodeURIComponent(input.replace(/\+/g, ' '));
	}

	/**
	 * Simple query string parser.
	 *
	 * @param {String} query The query string that needs to be parsed.
	 * @returns {Object}
	 * @api public
	 */
	function querystring(query) {
	  var parser = /([^=?&]+)=?([^&]*)/g
	    , result = {}
	    , part;

	  while (part = parser.exec(query)) {
	    var key = decode(part[1])
	      , value = decode(part[2]);

	    //
	    // Prevent overriding of existing properties. This ensures that build-in
	    // methods like `toString` or __proto__ are not overriden by malicious
	    // querystrings.
	    //
	    if (key in result) continue;
	    result[key] = value;
	  }

	  return result;
	}

	/**
	 * Transform a query string to an object.
	 *
	 * @param {Object} obj Object that should be transformed.
	 * @param {String} prefix Optional prefix.
	 * @returns {String}
	 * @api public
	 */
	function querystringify(obj, prefix) {
	  prefix = prefix || '';

	  var pairs = [];

	  //
	  // Optionally prefix with a '?' if needed
	  //
	  if ('string' !== typeof prefix) prefix = '?';

	  for (var key in obj) {
	    if (has.call(obj, key)) {
	      pairs.push(encodeURIComponent(key) +'='+ encodeURIComponent(obj[key]));
	    }
	  }

	  return pairs.length ? prefix + pairs.join('&') : '';
	}

	//
	// Expose the module.
	//
	exports.stringify = querystringify;
	exports.parse = querystring;


/***/ },
/* 19 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {/**
	 * This is the web browser implementation of `debug()`.
	 *
	 * Expose `debug()` as the module.
	 */

	exports = module.exports = __webpack_require__(20);
	exports.log = log;
	exports.formatArgs = formatArgs;
	exports.save = save;
	exports.load = load;
	exports.useColors = useColors;
	exports.storage = 'undefined' != typeof chrome
	               && 'undefined' != typeof chrome.storage
	                  ? chrome.storage.local
	                  : localstorage();

	/**
	 * Colors.
	 */

	exports.colors = [
	  'lightseagreen',
	  'forestgreen',
	  'goldenrod',
	  'dodgerblue',
	  'darkorchid',
	  'crimson'
	];

	/**
	 * Currently only WebKit-based Web Inspectors, Firefox >= v31,
	 * and the Firebug extension (any Firefox version) are known
	 * to support "%c" CSS customizations.
	 *
	 * TODO: add a `localStorage` variable to explicitly enable/disable colors
	 */

	function useColors() {
	  // NB: In an Electron preload script, document will be defined but not fully
	  // initialized. Since we know we're in Chrome, we'll just detect this case
	  // explicitly
	  if (typeof window !== 'undefined' && window.process && window.process.type === 'renderer') {
	    return true;
	  }

	  // is webkit? http://stackoverflow.com/a/16459606/376773
	  // document is undefined in react-native: https://github.com/facebook/react-native/pull/1632
	  return (typeof document !== 'undefined' && document.documentElement && document.documentElement.style && document.documentElement.style.WebkitAppearance) ||
	    // is firebug? http://stackoverflow.com/a/398120/376773
	    (typeof window !== 'undefined' && window.console && (window.console.firebug || (window.console.exception && window.console.table))) ||
	    // is firefox >= v31?
	    // https://developer.mozilla.org/en-US/docs/Tools/Web_Console#Styling_messages
	    (typeof navigator !== 'undefined' && navigator.userAgent && navigator.userAgent.toLowerCase().match(/firefox\/(\d+)/) && parseInt(RegExp.$1, 10) >= 31) ||
	    // double check webkit in userAgent just in case we are in a worker
	    (typeof navigator !== 'undefined' && navigator.userAgent && navigator.userAgent.toLowerCase().match(/applewebkit\/(\d+)/));
	}

	/**
	 * Map %j to `JSON.stringify()`, since no Web Inspectors do that by default.
	 */

	exports.formatters.j = function(v) {
	  try {
	    return JSON.stringify(v);
	  } catch (err) {
	    return '[UnexpectedJSONParseError]: ' + err.message;
	  }
	};


	/**
	 * Colorize log arguments if enabled.
	 *
	 * @api public
	 */

	function formatArgs(args) {
	  var useColors = this.useColors;

	  args[0] = (useColors ? '%c' : '')
	    + this.namespace
	    + (useColors ? ' %c' : ' ')
	    + args[0]
	    + (useColors ? '%c ' : ' ')
	    + '+' + exports.humanize(this.diff);

	  if (!useColors) return;

	  var c = 'color: ' + this.color;
	  args.splice(1, 0, c, 'color: inherit')

	  // the final "%c" is somewhat tricky, because there could be other
	  // arguments passed either before or after the %c, so we need to
	  // figure out the correct index to insert the CSS into
	  var index = 0;
	  var lastC = 0;
	  args[0].replace(/%[a-zA-Z%]/g, function(match) {
	    if ('%%' === match) return;
	    index++;
	    if ('%c' === match) {
	      // we only are interested in the *last* %c
	      // (the user may have provided their own)
	      lastC = index;
	    }
	  });

	  args.splice(lastC, 0, c);
	}

	/**
	 * Invokes `console.log()` when available.
	 * No-op when `console.log` is not a "function".
	 *
	 * @api public
	 */

	function log() {
	  // this hackery is required for IE8/9, where
	  // the `console.log` function doesn't have 'apply'
	  return 'object' === typeof console
	    && console.log
	    && Function.prototype.apply.call(console.log, console, arguments);
	}

	/**
	 * Save `namespaces`.
	 *
	 * @param {String} namespaces
	 * @api private
	 */

	function save(namespaces) {
	  try {
	    if (null == namespaces) {
	      exports.storage.removeItem('debug');
	    } else {
	      exports.storage.debug = namespaces;
	    }
	  } catch(e) {}
	}

	/**
	 * Load `namespaces`.
	 *
	 * @return {String} returns the previously persisted debug modes
	 * @api private
	 */

	function load() {
	  var r;
	  try {
	    r = exports.storage.debug;
	  } catch(e) {}

	  // If debug isn't set in LS, and we're in Electron, try to load $DEBUG
	  if (!r && typeof process !== 'undefined' && 'env' in process) {
	    r = process.env.DEBUG;
	  }

	  return r;
	}

	/**
	 * Enable namespaces listed in `localStorage.debug` initially.
	 */

	exports.enable(load());

	/**
	 * Localstorage attempts to return the localstorage.
	 *
	 * This is necessary because safari throws
	 * when a user disables cookies/localstorage
	 * and you attempt to access it.
	 *
	 * @return {LocalStorage}
	 * @api private
	 */

	function localstorage() {
	  try {
	    return window.localStorage;
	  } catch (e) {}
	}

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 20 */
/***/ function(module, exports, __webpack_require__) {

	
	/**
	 * This is the common logic for both the Node.js and web browser
	 * implementations of `debug()`.
	 *
	 * Expose `debug()` as the module.
	 */

	exports = module.exports = createDebug.debug = createDebug['default'] = createDebug;
	exports.coerce = coerce;
	exports.disable = disable;
	exports.enable = enable;
	exports.enabled = enabled;
	exports.humanize = __webpack_require__(21);

	/**
	 * The currently active debug mode names, and names to skip.
	 */

	exports.names = [];
	exports.skips = [];

	/**
	 * Map of special "%n" handling functions, for the debug "format" argument.
	 *
	 * Valid key names are a single, lower or upper-case letter, i.e. "n" and "N".
	 */

	exports.formatters = {};

	/**
	 * Previous log timestamp.
	 */

	var prevTime;

	/**
	 * Select a color.
	 * @param {String} namespace
	 * @return {Number}
	 * @api private
	 */

	function selectColor(namespace) {
	  var hash = 0, i;

	  for (i in namespace) {
	    hash  = ((hash << 5) - hash) + namespace.charCodeAt(i);
	    hash |= 0; // Convert to 32bit integer
	  }

	  return exports.colors[Math.abs(hash) % exports.colors.length];
	}

	/**
	 * Create a debugger with the given `namespace`.
	 *
	 * @param {String} namespace
	 * @return {Function}
	 * @api public
	 */

	function createDebug(namespace) {

	  function debug() {
	    // disabled?
	    if (!debug.enabled) return;

	    var self = debug;

	    // set `diff` timestamp
	    var curr = +new Date();
	    var ms = curr - (prevTime || curr);
	    self.diff = ms;
	    self.prev = prevTime;
	    self.curr = curr;
	    prevTime = curr;

	    // turn the `arguments` into a proper Array
	    var args = new Array(arguments.length);
	    for (var i = 0; i < args.length; i++) {
	      args[i] = arguments[i];
	    }

	    args[0] = exports.coerce(args[0]);

	    if ('string' !== typeof args[0]) {
	      // anything else let's inspect with %O
	      args.unshift('%O');
	    }

	    // apply any `formatters` transformations
	    var index = 0;
	    args[0] = args[0].replace(/%([a-zA-Z%])/g, function(match, format) {
	      // if we encounter an escaped % then don't increase the array index
	      if (match === '%%') return match;
	      index++;
	      var formatter = exports.formatters[format];
	      if ('function' === typeof formatter) {
	        var val = args[index];
	        match = formatter.call(self, val);

	        // now we need to remove `args[index]` since it's inlined in the `format`
	        args.splice(index, 1);
	        index--;
	      }
	      return match;
	    });

	    // apply env-specific formatting (colors, etc.)
	    exports.formatArgs.call(self, args);

	    var logFn = debug.log || exports.log || console.log.bind(console);
	    logFn.apply(self, args);
	  }

	  debug.namespace = namespace;
	  debug.enabled = exports.enabled(namespace);
	  debug.useColors = exports.useColors();
	  debug.color = selectColor(namespace);

	  // env-specific initialization logic for debug instances
	  if ('function' === typeof exports.init) {
	    exports.init(debug);
	  }

	  return debug;
	}

	/**
	 * Enables a debug mode by namespaces. This can include modes
	 * separated by a colon and wildcards.
	 *
	 * @param {String} namespaces
	 * @api public
	 */

	function enable(namespaces) {
	  exports.save(namespaces);

	  exports.names = [];
	  exports.skips = [];

	  var split = (typeof namespaces === 'string' ? namespaces : '').split(/[\s,]+/);
	  var len = split.length;

	  for (var i = 0; i < len; i++) {
	    if (!split[i]) continue; // ignore empty strings
	    namespaces = split[i].replace(/\*/g, '.*?');
	    if (namespaces[0] === '-') {
	      exports.skips.push(new RegExp('^' + namespaces.substr(1) + '$'));
	    } else {
	      exports.names.push(new RegExp('^' + namespaces + '$'));
	    }
	  }
	}

	/**
	 * Disable debug output.
	 *
	 * @api public
	 */

	function disable() {
	  exports.enable('');
	}

	/**
	 * Returns true if the given mode name is enabled, false otherwise.
	 *
	 * @param {String} name
	 * @return {Boolean}
	 * @api public
	 */

	function enabled(name) {
	  var i, len;
	  for (i = 0, len = exports.skips.length; i < len; i++) {
	    if (exports.skips[i].test(name)) {
	      return false;
	    }
	  }
	  for (i = 0, len = exports.names.length; i < len; i++) {
	    if (exports.names[i].test(name)) {
	      return true;
	    }
	  }
	  return false;
	}

	/**
	 * Coerce `val`.
	 *
	 * @param {Mixed} val
	 * @return {Mixed}
	 * @api private
	 */

	function coerce(val) {
	  if (val instanceof Error) return val.stack || val.message;
	  return val;
	}


/***/ },
/* 21 */
/***/ function(module, exports) {

	/**
	 * Helpers.
	 */

	var s = 1000;
	var m = s * 60;
	var h = m * 60;
	var d = h * 24;
	var y = d * 365.25;

	/**
	 * Parse or format the given `val`.
	 *
	 * Options:
	 *
	 *  - `long` verbose formatting [false]
	 *
	 * @param {String|Number} val
	 * @param {Object} [options]
	 * @throws {Error} throw an error if val is not a non-empty string or a number
	 * @return {String|Number}
	 * @api public
	 */

	module.exports = function(val, options) {
	  options = options || {};
	  var type = typeof val;
	  if (type === 'string' && val.length > 0) {
	    return parse(val);
	  } else if (type === 'number' && isNaN(val) === false) {
	    return options.long ? fmtLong(val) : fmtShort(val);
	  }
	  throw new Error(
	    'val is not a non-empty string or a valid number. val=' +
	      JSON.stringify(val)
	  );
	};

	/**
	 * Parse the given `str` and return milliseconds.
	 *
	 * @param {String} str
	 * @return {Number}
	 * @api private
	 */

	function parse(str) {
	  str = String(str);
	  if (str.length > 100) {
	    return;
	  }
	  var match = /^((?:\d+)?\.?\d+) *(milliseconds?|msecs?|ms|seconds?|secs?|s|minutes?|mins?|m|hours?|hrs?|h|days?|d|years?|yrs?|y)?$/i.exec(
	    str
	  );
	  if (!match) {
	    return;
	  }
	  var n = parseFloat(match[1]);
	  var type = (match[2] || 'ms').toLowerCase();
	  switch (type) {
	    case 'years':
	    case 'year':
	    case 'yrs':
	    case 'yr':
	    case 'y':
	      return n * y;
	    case 'days':
	    case 'day':
	    case 'd':
	      return n * d;
	    case 'hours':
	    case 'hour':
	    case 'hrs':
	    case 'hr':
	    case 'h':
	      return n * h;
	    case 'minutes':
	    case 'minute':
	    case 'mins':
	    case 'min':
	    case 'm':
	      return n * m;
	    case 'seconds':
	    case 'second':
	    case 'secs':
	    case 'sec':
	    case 's':
	      return n * s;
	    case 'milliseconds':
	    case 'millisecond':
	    case 'msecs':
	    case 'msec':
	    case 'ms':
	      return n;
	    default:
	      return undefined;
	  }
	}

	/**
	 * Short format for `ms`.
	 *
	 * @param {Number} ms
	 * @return {String}
	 * @api private
	 */

	function fmtShort(ms) {
	  if (ms >= d) {
	    return Math.round(ms / d) + 'd';
	  }
	  if (ms >= h) {
	    return Math.round(ms / h) + 'h';
	  }
	  if (ms >= m) {
	    return Math.round(ms / m) + 'm';
	  }
	  if (ms >= s) {
	    return Math.round(ms / s) + 's';
	  }
	  return ms + 'ms';
	}

	/**
	 * Long format for `ms`.
	 *
	 * @param {Number} ms
	 * @return {String}
	 * @api private
	 */

	function fmtLong(ms) {
	  return plural(ms, d, 'day') ||
	    plural(ms, h, 'hour') ||
	    plural(ms, m, 'minute') ||
	    plural(ms, s, 'second') ||
	    ms + ' ms';
	}

	/**
	 * Pluralization helper.
	 */

	function plural(ms, n, name) {
	  if (ms < n) {
	    return;
	  }
	  if (ms < n * 1.5) {
	    return Math.floor(ms / n) + ' ' + name;
	  }
	  return Math.ceil(ms / n) + ' ' + name + 's';
	}


/***/ },
/* 22 */
/***/ function(module, exports) {

	if (typeof Object.create === 'function') {
	  // implementation from standard node.js 'util' module
	  module.exports = function inherits(ctor, superCtor) {
	    ctor.super_ = superCtor
	    ctor.prototype = Object.create(superCtor.prototype, {
	      constructor: {
	        value: ctor,
	        enumerable: false,
	        writable: true,
	        configurable: true
	      }
	    });
	  };
	} else {
	  // old school shim for old browsers
	  module.exports = function inherits(ctor, superCtor) {
	    ctor.super_ = superCtor
	    var TempCtor = function () {}
	    TempCtor.prototype = superCtor.prototype
	    ctor.prototype = new TempCtor()
	    ctor.prototype.constructor = ctor
	  }
	}


/***/ },
/* 23 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , EventTarget = __webpack_require__(24)
	  ;

	function EventEmitter() {
	  EventTarget.call(this);
	}

	inherits(EventEmitter, EventTarget);

	EventEmitter.prototype.removeAllListeners = function(type) {
	  if (type) {
	    delete this._listeners[type];
	  } else {
	    this._listeners = {};
	  }
	};

	EventEmitter.prototype.once = function(type, listener) {
	  var self = this
	    , fired = false;

	  function g() {
	    self.removeListener(type, g);

	    if (!fired) {
	      fired = true;
	      listener.apply(this, arguments);
	    }
	  }

	  this.on(type, g);
	};

	EventEmitter.prototype.emit = function() {
	  var type = arguments[0];
	  var listeners = this._listeners[type];
	  if (!listeners) {
	    return;
	  }
	  // equivalent of Array.prototype.slice.call(arguments, 1);
	  var l = arguments.length;
	  var args = new Array(l - 1);
	  for (var ai = 1; ai < l; ai++) {
	    args[ai - 1] = arguments[ai];
	  }
	  for (var i = 0; i < listeners.length; i++) {
	    listeners[i].apply(this, args);
	  }
	};

	EventEmitter.prototype.on = EventEmitter.prototype.addListener = EventTarget.prototype.addEventListener;
	EventEmitter.prototype.removeListener = EventTarget.prototype.removeEventListener;

	module.exports.EventEmitter = EventEmitter;


/***/ },
/* 24 */
/***/ function(module, exports) {

	'use strict';

	/* Simplified implementation of DOM2 EventTarget.
	 *   http://www.w3.org/TR/DOM-Level-2-Events/events.html#Events-EventTarget
	 */

	function EventTarget() {
	  this._listeners = {};
	}

	EventTarget.prototype.addEventListener = function(eventType, listener) {
	  if (!(eventType in this._listeners)) {
	    this._listeners[eventType] = [];
	  }
	  var arr = this._listeners[eventType];
	  // #4
	  if (arr.indexOf(listener) === -1) {
	    // Make a copy so as not to interfere with a current dispatchEvent.
	    arr = arr.concat([listener]);
	  }
	  this._listeners[eventType] = arr;
	};

	EventTarget.prototype.removeEventListener = function(eventType, listener) {
	  var arr = this._listeners[eventType];
	  if (!arr) {
	    return;
	  }
	  var idx = arr.indexOf(listener);
	  if (idx !== -1) {
	    if (arr.length > 1) {
	      // Make a copy so as not to interfere with a current dispatchEvent.
	      this._listeners[eventType] = arr.slice(0, idx).concat(arr.slice(idx + 1));
	    } else {
	      delete this._listeners[eventType];
	    }
	    return;
	  }
	};

	EventTarget.prototype.dispatchEvent = function() {
	  var event = arguments[0];
	  var t = event.type;
	  // equivalent of Array.prototype.slice.call(arguments, 0);
	  var args = arguments.length === 1 ? [event] : Array.apply(null, arguments);
	  // TODO: This doesn't match the real behavior; per spec, onfoo get
	  // their place in line from the /first/ time they're set from
	  // non-null. Although WebKit bumps it to the end every time it's
	  // set.
	  if (this['on' + t]) {
	    this['on' + t].apply(this, args);
	  }
	  if (t in this._listeners) {
	    // Grab a reference to the listeners list. removeEventListener may alter the list.
	    var listeners = this._listeners[t];
	    for (var i = 0; i < listeners.length; i++) {
	      listeners[i].apply(this, args);
	    }
	  }
	};

	module.exports = EventTarget;


/***/ },
/* 25 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var Driver = global.WebSocket || global.MozWebSocket;
	if (Driver) {
		module.exports = function WebSocketBrowserDriver(url) {
			return new Driver(url);
		};
	} else {
		module.exports = undefined;
	}

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 26 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var inherits = __webpack_require__(22)
	  , AjaxBasedTransport = __webpack_require__(27)
	  , XhrReceiver = __webpack_require__(31)
	  , XHRCorsObject = __webpack_require__(32)
	  , XHRLocalObject = __webpack_require__(34)
	  , browser = __webpack_require__(35)
	  ;

	function XhrStreamingTransport(transUrl) {
	  if (!XHRLocalObject.enabled && !XHRCorsObject.enabled) {
	    throw new Error('Transport created when disabled');
	  }
	  AjaxBasedTransport.call(this, transUrl, '/xhr_streaming', XhrReceiver, XHRCorsObject);
	}

	inherits(XhrStreamingTransport, AjaxBasedTransport);

	XhrStreamingTransport.enabled = function(info) {
	  if (info.nullOrigin) {
	    return false;
	  }
	  // Opera doesn't support xhr-streaming #60
	  // But it might be able to #92
	  if (browser.isOpera()) {
	    return false;
	  }

	  return XHRCorsObject.enabled;
	};

	XhrStreamingTransport.transportName = 'xhr-streaming';
	XhrStreamingTransport.roundTrips = 2; // preflight, ajax

	// Safari gets confused when a streaming ajax request is started
	// before onload. This causes the load indicator to spin indefinetely.
	// Only require body when used in a browser
	XhrStreamingTransport.needBody = !!global.document;

	module.exports = XhrStreamingTransport;

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 27 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var inherits = __webpack_require__(22)
	  , urlUtils = __webpack_require__(15)
	  , SenderReceiver = __webpack_require__(28)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:ajax-based');
	}

	function createAjaxSender(AjaxObject) {
	  return function(url, payload, callback) {
	    debug('create ajax sender', url, payload);
	    var opt = {};
	    if (typeof payload === 'string') {
	      opt.headers = {'Content-type': 'text/plain'};
	    }
	    var ajaxUrl = urlUtils.addPath(url, '/xhr_send');
	    var xo = new AjaxObject('POST', ajaxUrl, payload, opt);
	    xo.once('finish', function(status) {
	      debug('finish', status);
	      xo = null;

	      if (status !== 200 && status !== 204) {
	        return callback(new Error('http status ' + status));
	      }
	      callback();
	    });
	    return function() {
	      debug('abort');
	      xo.close();
	      xo = null;

	      var err = new Error('Aborted');
	      err.code = 1000;
	      callback(err);
	    };
	  };
	}

	function AjaxBasedTransport(transUrl, urlSuffix, Receiver, AjaxObject) {
	  SenderReceiver.call(this, transUrl, urlSuffix, createAjaxSender(AjaxObject), Receiver, AjaxObject);
	}

	inherits(AjaxBasedTransport, SenderReceiver);

	module.exports = AjaxBasedTransport;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 28 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var inherits = __webpack_require__(22)
	  , urlUtils = __webpack_require__(15)
	  , BufferedSender = __webpack_require__(29)
	  , Polling = __webpack_require__(30)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:sender-receiver');
	}

	function SenderReceiver(transUrl, urlSuffix, senderFunc, Receiver, AjaxObject) {
	  var pollUrl = urlUtils.addPath(transUrl, urlSuffix);
	  debug(pollUrl);
	  var self = this;
	  BufferedSender.call(this, transUrl, senderFunc);

	  this.poll = new Polling(Receiver, pollUrl, AjaxObject);
	  this.poll.on('message', function(msg) {
	    debug('poll message', msg);
	    self.emit('message', msg);
	  });
	  this.poll.once('close', function(code, reason) {
	    debug('poll close', code, reason);
	    self.poll = null;
	    self.emit('close', code, reason);
	    self.close();
	  });
	}

	inherits(SenderReceiver, BufferedSender);

	SenderReceiver.prototype.close = function() {
	  BufferedSender.prototype.close.call(this);
	  debug('close');
	  this.removeAllListeners();
	  if (this.poll) {
	    this.poll.abort();
	    this.poll = null;
	  }
	};

	module.exports = SenderReceiver;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 29 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:buffered-sender');
	}

	function BufferedSender(url, sender) {
	  debug(url);
	  EventEmitter.call(this);
	  this.sendBuffer = [];
	  this.sender = sender;
	  this.url = url;
	}

	inherits(BufferedSender, EventEmitter);

	BufferedSender.prototype.send = function(message) {
	  debug('send', message);
	  this.sendBuffer.push(message);
	  if (!this.sendStop) {
	    this.sendSchedule();
	  }
	};

	// For polling transports in a situation when in the message callback,
	// new message is being send. If the sending connection was started
	// before receiving one, it is possible to saturate the network and
	// timeout due to the lack of receiving socket. To avoid that we delay
	// sending messages by some small time, in order to let receiving
	// connection be started beforehand. This is only a halfmeasure and
	// does not fix the big problem, but it does make the tests go more
	// stable on slow networks.
	BufferedSender.prototype.sendScheduleWait = function() {
	  debug('sendScheduleWait');
	  var self = this;
	  var tref;
	  this.sendStop = function() {
	    debug('sendStop');
	    self.sendStop = null;
	    clearTimeout(tref);
	  };
	  tref = setTimeout(function() {
	    debug('timeout');
	    self.sendStop = null;
	    self.sendSchedule();
	  }, 25);
	};

	BufferedSender.prototype.sendSchedule = function() {
	  debug('sendSchedule', this.sendBuffer.length);
	  var self = this;
	  if (this.sendBuffer.length > 0) {
	    var payload = '[' + this.sendBuffer.join(',') + ']';
	    this.sendStop = this.sender(this.url, payload, function(err) {
	      self.sendStop = null;
	      if (err) {
	        debug('error', err);
	        self.emit('close', err.code || 1006, 'Sending error: ' + err);
	        self.close();
	      } else {
	        self.sendScheduleWait();
	      }
	    });
	    this.sendBuffer = [];
	  }
	};

	BufferedSender.prototype._cleanup = function() {
	  debug('_cleanup');
	  this.removeAllListeners();
	};

	BufferedSender.prototype.close = function() {
	  debug('close');
	  this._cleanup();
	  if (this.sendStop) {
	    this.sendStop();
	    this.sendStop = null;
	  }
	};

	module.exports = BufferedSender;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 30 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:polling');
	}

	function Polling(Receiver, receiveUrl, AjaxObject) {
	  debug(receiveUrl);
	  EventEmitter.call(this);
	  this.Receiver = Receiver;
	  this.receiveUrl = receiveUrl;
	  this.AjaxObject = AjaxObject;
	  this._scheduleReceiver();
	}

	inherits(Polling, EventEmitter);

	Polling.prototype._scheduleReceiver = function() {
	  debug('_scheduleReceiver');
	  var self = this;
	  var poll = this.poll = new this.Receiver(this.receiveUrl, this.AjaxObject);

	  poll.on('message', function(msg) {
	    debug('message', msg);
	    self.emit('message', msg);
	  });

	  poll.once('close', function(code, reason) {
	    debug('close', code, reason, self.pollIsClosing);
	    self.poll = poll = null;

	    if (!self.pollIsClosing) {
	      if (reason === 'network') {
	        self._scheduleReceiver();
	      } else {
	        self.emit('close', code || 1006, reason);
	        self.removeAllListeners();
	      }
	    }
	  });
	};

	Polling.prototype.abort = function() {
	  debug('abort');
	  this.removeAllListeners();
	  this.pollIsClosing = true;
	  if (this.poll) {
	    this.poll.abort();
	  }
	};

	module.exports = Polling;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 31 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:receiver:xhr');
	}

	function XhrReceiver(url, AjaxObject) {
	  debug(url);
	  EventEmitter.call(this);
	  var self = this;

	  this.bufferPosition = 0;

	  this.xo = new AjaxObject('POST', url, null);
	  this.xo.on('chunk', this._chunkHandler.bind(this));
	  this.xo.once('finish', function(status, text) {
	    debug('finish', status, text);
	    self._chunkHandler(status, text);
	    self.xo = null;
	    var reason = status === 200 ? 'network' : 'permanent';
	    debug('close', reason);
	    self.emit('close', null, reason);
	    self._cleanup();
	  });
	}

	inherits(XhrReceiver, EventEmitter);

	XhrReceiver.prototype._chunkHandler = function(status, text) {
	  debug('_chunkHandler', status);
	  if (status !== 200 || !text) {
	    return;
	  }

	  for (var idx = -1; ; this.bufferPosition += idx + 1) {
	    var buf = text.slice(this.bufferPosition);
	    idx = buf.indexOf('\n');
	    if (idx === -1) {
	      break;
	    }
	    var msg = buf.slice(0, idx);
	    if (msg) {
	      debug('message', msg);
	      this.emit('message', msg);
	    }
	  }
	};

	XhrReceiver.prototype._cleanup = function() {
	  debug('_cleanup');
	  this.removeAllListeners();
	};

	XhrReceiver.prototype.abort = function() {
	  debug('abort');
	  if (this.xo) {
	    this.xo.close();
	    debug('close');
	    this.emit('close', null, 'user');
	    this.xo = null;
	  }
	  this._cleanup();
	};

	module.exports = XhrReceiver;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 32 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , XhrDriver = __webpack_require__(33)
	  ;

	function XHRCorsObject(method, url, payload, opts) {
	  XhrDriver.call(this, method, url, payload, opts);
	}

	inherits(XHRCorsObject, XhrDriver);

	XHRCorsObject.enabled = XhrDriver.enabled && XhrDriver.supportsCORS;

	module.exports = XHRCorsObject;


/***/ },
/* 33 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global, process) {'use strict';

	var EventEmitter = __webpack_require__(23).EventEmitter
	  , inherits = __webpack_require__(22)
	  , utils = __webpack_require__(12)
	  , urlUtils = __webpack_require__(15)
	  , XHR = global.XMLHttpRequest
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:browser:xhr');
	}

	function AbstractXHRObject(method, url, payload, opts) {
	  debug(method, url);
	  var self = this;
	  EventEmitter.call(this);

	  setTimeout(function () {
	    self._start(method, url, payload, opts);
	  }, 0);
	}

	inherits(AbstractXHRObject, EventEmitter);

	AbstractXHRObject.prototype._start = function(method, url, payload, opts) {
	  var self = this;

	  try {
	    this.xhr = new XHR();
	  } catch (x) {
	    // intentionally empty
	  }

	  if (!this.xhr) {
	    debug('no xhr');
	    this.emit('finish', 0, 'no xhr support');
	    this._cleanup();
	    return;
	  }

	  // several browsers cache POSTs
	  url = urlUtils.addQuery(url, 't=' + (+new Date()));

	  // Explorer tends to keep connection open, even after the
	  // tab gets closed: http://bugs.jquery.com/ticket/5280
	  this.unloadRef = utils.unloadAdd(function() {
	    debug('unload cleanup');
	    self._cleanup(true);
	  });
	  try {
	    this.xhr.open(method, url, true);
	    if (this.timeout && 'timeout' in this.xhr) {
	      this.xhr.timeout = this.timeout;
	      this.xhr.ontimeout = function() {
	        debug('xhr timeout');
	        self.emit('finish', 0, '');
	        self._cleanup(false);
	      };
	    }
	  } catch (e) {
	    debug('exception', e);
	    // IE raises an exception on wrong port.
	    this.emit('finish', 0, '');
	    this._cleanup(false);
	    return;
	  }

	  if ((!opts || !opts.noCredentials) && AbstractXHRObject.supportsCORS) {
	    debug('withCredentials');
	    // Mozilla docs says https://developer.mozilla.org/en/XMLHttpRequest :
	    // "This never affects same-site requests."

	    this.xhr.withCredentials = true;
	  }
	  if (opts && opts.headers) {
	    for (var key in opts.headers) {
	      this.xhr.setRequestHeader(key, opts.headers[key]);
	    }
	  }

	  this.xhr.onreadystatechange = function() {
	    if (self.xhr) {
	      var x = self.xhr;
	      var text, status;
	      debug('readyState', x.readyState);
	      switch (x.readyState) {
	      case 3:
	        // IE doesn't like peeking into responseText or status
	        // on Microsoft.XMLHTTP and readystate=3
	        try {
	          status = x.status;
	          text = x.responseText;
	        } catch (e) {
	          // intentionally empty
	        }
	        debug('status', status);
	        // IE returns 1223 for 204: http://bugs.jquery.com/ticket/1450
	        if (status === 1223) {
	          status = 204;
	        }

	        // IE does return readystate == 3 for 404 answers.
	        if (status === 200 && text && text.length > 0) {
	          debug('chunk');
	          self.emit('chunk', status, text);
	        }
	        break;
	      case 4:
	        status = x.status;
	        debug('status', status);
	        // IE returns 1223 for 204: http://bugs.jquery.com/ticket/1450
	        if (status === 1223) {
	          status = 204;
	        }
	        // IE returns this for a bad port
	        // http://msdn.microsoft.com/en-us/library/windows/desktop/aa383770(v=vs.85).aspx
	        if (status === 12005 || status === 12029) {
	          status = 0;
	        }

	        debug('finish', status, x.responseText);
	        self.emit('finish', status, x.responseText);
	        self._cleanup(false);
	        break;
	      }
	    }
	  };

	  try {
	    self.xhr.send(payload);
	  } catch (e) {
	    self.emit('finish', 0, '');
	    self._cleanup(false);
	  }
	};

	AbstractXHRObject.prototype._cleanup = function(abort) {
	  debug('cleanup');
	  if (!this.xhr) {
	    return;
	  }
	  this.removeAllListeners();
	  utils.unloadDel(this.unloadRef);

	  // IE needs this field to be a function
	  this.xhr.onreadystatechange = function() {};
	  if (this.xhr.ontimeout) {
	    this.xhr.ontimeout = null;
	  }

	  if (abort) {
	    try {
	      this.xhr.abort();
	    } catch (x) {
	      // intentionally empty
	    }
	  }
	  this.unloadRef = this.xhr = null;
	};

	AbstractXHRObject.prototype.close = function() {
	  debug('close');
	  this._cleanup(true);
	};

	AbstractXHRObject.enabled = !!XHR;
	// override XMLHttpRequest for IE6/7
	// obfuscate to avoid firewalls
	var axo = ['Active'].concat('Object').join('X');
	if (!AbstractXHRObject.enabled && (axo in global)) {
	  debug('overriding xmlhttprequest');
	  XHR = function() {
	    try {
	      return new global[axo]('Microsoft.XMLHTTP');
	    } catch (e) {
	      return null;
	    }
	  };
	  AbstractXHRObject.enabled = !!new XHR();
	}

	var cors = false;
	try {
	  cors = 'withCredentials' in new XHR();
	} catch (ignored) {
	  // intentionally empty
	}

	AbstractXHRObject.supportsCORS = cors;

	module.exports = AbstractXHRObject;

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }()), __webpack_require__(11)))

/***/ },
/* 34 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , XhrDriver = __webpack_require__(33)
	  ;

	function XHRLocalObject(method, url, payload /*, opts */) {
	  XhrDriver.call(this, method, url, payload, {
	    noCredentials: true
	  });
	}

	inherits(XHRLocalObject, XhrDriver);

	XHRLocalObject.enabled = XhrDriver.enabled;

	module.exports = XHRLocalObject;


/***/ },
/* 35 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	module.exports = {
	  isOpera: function() {
	    return global.navigator &&
	      /opera/i.test(global.navigator.userAgent);
	  }

	, isKonqueror: function() {
	    return global.navigator &&
	      /konqueror/i.test(global.navigator.userAgent);
	  }

	  // #187 wrap document.domain in try/catch because of WP8 from file:///
	, hasDomain: function () {
	    // non-browser client always has a domain
	    if (!global.document) {
	      return true;
	    }

	    try {
	      return !!global.document.domain;
	    } catch (e) {
	      return false;
	    }
	  }
	};

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 36 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , AjaxBasedTransport = __webpack_require__(27)
	  , XhrReceiver = __webpack_require__(31)
	  , XDRObject = __webpack_require__(37)
	  ;

	// According to:
	//   http://stackoverflow.com/questions/1641507/detect-browser-support-for-cross-domain-xmlhttprequests
	//   http://hacks.mozilla.org/2009/07/cross-site-xmlhttprequest-with-cors/

	function XdrStreamingTransport(transUrl) {
	  if (!XDRObject.enabled) {
	    throw new Error('Transport created when disabled');
	  }
	  AjaxBasedTransport.call(this, transUrl, '/xhr_streaming', XhrReceiver, XDRObject);
	}

	inherits(XdrStreamingTransport, AjaxBasedTransport);

	XdrStreamingTransport.enabled = function(info) {
	  if (info.cookie_needed || info.nullOrigin) {
	    return false;
	  }
	  return XDRObject.enabled && info.sameScheme;
	};

	XdrStreamingTransport.transportName = 'xdr-streaming';
	XdrStreamingTransport.roundTrips = 2; // preflight, ajax

	module.exports = XdrStreamingTransport;


/***/ },
/* 37 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	var EventEmitter = __webpack_require__(23).EventEmitter
	  , inherits = __webpack_require__(22)
	  , eventUtils = __webpack_require__(12)
	  , browser = __webpack_require__(35)
	  , urlUtils = __webpack_require__(15)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:sender:xdr');
	}

	// References:
	//   http://ajaxian.com/archives/100-line-ajax-wrapper
	//   http://msdn.microsoft.com/en-us/library/cc288060(v=VS.85).aspx

	function XDRObject(method, url, payload) {
	  debug(method, url);
	  var self = this;
	  EventEmitter.call(this);

	  setTimeout(function() {
	    self._start(method, url, payload);
	  }, 0);
	}

	inherits(XDRObject, EventEmitter);

	XDRObject.prototype._start = function(method, url, payload) {
	  debug('_start');
	  var self = this;
	  var xdr = new global.XDomainRequest();
	  // IE caches even POSTs
	  url = urlUtils.addQuery(url, 't=' + (+new Date()));

	  xdr.onerror = function() {
	    debug('onerror');
	    self._error();
	  };
	  xdr.ontimeout = function() {
	    debug('ontimeout');
	    self._error();
	  };
	  xdr.onprogress = function() {
	    debug('progress', xdr.responseText);
	    self.emit('chunk', 200, xdr.responseText);
	  };
	  xdr.onload = function() {
	    debug('load');
	    self.emit('finish', 200, xdr.responseText);
	    self._cleanup(false);
	  };
	  this.xdr = xdr;
	  this.unloadRef = eventUtils.unloadAdd(function() {
	    self._cleanup(true);
	  });
	  try {
	    // Fails with AccessDenied if port number is bogus
	    this.xdr.open(method, url);
	    if (this.timeout) {
	      this.xdr.timeout = this.timeout;
	    }
	    this.xdr.send(payload);
	  } catch (x) {
	    this._error();
	  }
	};

	XDRObject.prototype._error = function() {
	  this.emit('finish', 0, '');
	  this._cleanup(false);
	};

	XDRObject.prototype._cleanup = function(abort) {
	  debug('cleanup', abort);
	  if (!this.xdr) {
	    return;
	  }
	  this.removeAllListeners();
	  eventUtils.unloadDel(this.unloadRef);

	  this.xdr.ontimeout = this.xdr.onerror = this.xdr.onprogress = this.xdr.onload = null;
	  if (abort) {
	    try {
	      this.xdr.abort();
	    } catch (x) {
	      // intentionally empty
	    }
	  }
	  this.unloadRef = this.xdr = null;
	};

	XDRObject.prototype.close = function() {
	  debug('close');
	  this._cleanup(true);
	};

	// IE 8/9 if the request target uses the same scheme - #79
	XDRObject.enabled = !!(global.XDomainRequest && browser.hasDomain());

	module.exports = XDRObject;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 38 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , AjaxBasedTransport = __webpack_require__(27)
	  , EventSourceReceiver = __webpack_require__(39)
	  , XHRCorsObject = __webpack_require__(32)
	  , EventSourceDriver = __webpack_require__(40)
	  ;

	function EventSourceTransport(transUrl) {
	  if (!EventSourceTransport.enabled()) {
	    throw new Error('Transport created when disabled');
	  }

	  AjaxBasedTransport.call(this, transUrl, '/eventsource', EventSourceReceiver, XHRCorsObject);
	}

	inherits(EventSourceTransport, AjaxBasedTransport);

	EventSourceTransport.enabled = function() {
	  return !!EventSourceDriver;
	};

	EventSourceTransport.transportName = 'eventsource';
	EventSourceTransport.roundTrips = 2;

	module.exports = EventSourceTransport;


/***/ },
/* 39 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  , EventSourceDriver = __webpack_require__(40)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:receiver:eventsource');
	}

	function EventSourceReceiver(url) {
	  debug(url);
	  EventEmitter.call(this);

	  var self = this;
	  var es = this.es = new EventSourceDriver(url);
	  es.onmessage = function(e) {
	    debug('message', e.data);
	    self.emit('message', decodeURI(e.data));
	  };
	  es.onerror = function(e) {
	    debug('error', es.readyState, e);
	    // ES on reconnection has readyState = 0 or 1.
	    // on network error it's CLOSED = 2
	    var reason = (es.readyState !== 2 ? 'network' : 'permanent');
	    self._cleanup();
	    self._close(reason);
	  };
	}

	inherits(EventSourceReceiver, EventEmitter);

	EventSourceReceiver.prototype.abort = function() {
	  debug('abort');
	  this._cleanup();
	  this._close('user');
	};

	EventSourceReceiver.prototype._cleanup = function() {
	  debug('cleanup');
	  var es = this.es;
	  if (es) {
	    es.onmessage = es.onerror = null;
	    es.close();
	    this.es = null;
	  }
	};

	EventSourceReceiver.prototype._close = function(reason) {
	  debug('close', reason);
	  var self = this;
	  // Safari and chrome < 15 crash if we close window before
	  // waiting for ES cleanup. See:
	  // https://code.google.com/p/chromium/issues/detail?id=89155
	  setTimeout(function() {
	    self.emit('close', null, reason);
	    self.removeAllListeners();
	  }, 200);
	};

	module.exports = EventSourceReceiver;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 40 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(global) {module.exports = global.EventSource;

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 41 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var inherits = __webpack_require__(22)
	  , IframeTransport = __webpack_require__(42)
	  , objectUtils = __webpack_require__(47)
	  ;

	module.exports = function(transport) {

	  function IframeWrapTransport(transUrl, baseUrl) {
	    IframeTransport.call(this, transport.transportName, transUrl, baseUrl);
	  }

	  inherits(IframeWrapTransport, IframeTransport);

	  IframeWrapTransport.enabled = function(url, info) {
	    if (!global.document) {
	      return false;
	    }

	    var iframeInfo = objectUtils.extend({}, info);
	    iframeInfo.sameOrigin = true;
	    return transport.enabled(iframeInfo) && IframeTransport.enabled();
	  };

	  IframeWrapTransport.transportName = 'iframe-' + transport.transportName;
	  IframeWrapTransport.needBody = true;
	  IframeWrapTransport.roundTrips = IframeTransport.roundTrips + transport.roundTrips - 1; // html, javascript (2) + transport - no CORS (1)

	  IframeWrapTransport.facadeTransport = transport;

	  return IframeWrapTransport;
	};

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 42 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	// Few cool transports do work only for same-origin. In order to make
	// them work cross-domain we shall use iframe, served from the
	// remote domain. New browsers have capabilities to communicate with
	// cross domain iframe using postMessage(). In IE it was implemented
	// from IE 8+, but of course, IE got some details wrong:
	//    http://msdn.microsoft.com/en-us/library/cc197015(v=VS.85).aspx
	//    http://stevesouders.com/misc/test-postmessage.php

	var inherits = __webpack_require__(22)
	  , JSON3 = __webpack_require__(43)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  , version = __webpack_require__(45)
	  , urlUtils = __webpack_require__(15)
	  , iframeUtils = __webpack_require__(46)
	  , eventUtils = __webpack_require__(12)
	  , random = __webpack_require__(13)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:transport:iframe');
	}

	function IframeTransport(transport, transUrl, baseUrl) {
	  if (!IframeTransport.enabled()) {
	    throw new Error('Transport created when disabled');
	  }
	  EventEmitter.call(this);

	  var self = this;
	  this.origin = urlUtils.getOrigin(baseUrl);
	  this.baseUrl = baseUrl;
	  this.transUrl = transUrl;
	  this.transport = transport;
	  this.windowId = random.string(8);

	  var iframeUrl = urlUtils.addPath(baseUrl, '/iframe.html') + '#' + this.windowId;
	  debug(transport, transUrl, iframeUrl);

	  this.iframeObj = iframeUtils.createIframe(iframeUrl, function(r) {
	    debug('err callback');
	    self.emit('close', 1006, 'Unable to load an iframe (' + r + ')');
	    self.close();
	  });

	  this.onmessageCallback = this._message.bind(this);
	  eventUtils.attachEvent('message', this.onmessageCallback);
	}

	inherits(IframeTransport, EventEmitter);

	IframeTransport.prototype.close = function() {
	  debug('close');
	  this.removeAllListeners();
	  if (this.iframeObj) {
	    eventUtils.detachEvent('message', this.onmessageCallback);
	    try {
	      // When the iframe is not loaded, IE raises an exception
	      // on 'contentWindow'.
	      this.postMessage('c');
	    } catch (x) {
	      // intentionally empty
	    }
	    this.iframeObj.cleanup();
	    this.iframeObj = null;
	    this.onmessageCallback = this.iframeObj = null;
	  }
	};

	IframeTransport.prototype._message = function(e) {
	  debug('message', e.data);
	  if (!urlUtils.isOriginEqual(e.origin, this.origin)) {
	    debug('not same origin', e.origin, this.origin);
	    return;
	  }

	  var iframeMessage;
	  try {
	    iframeMessage = JSON3.parse(e.data);
	  } catch (ignored) {
	    debug('bad json', e.data);
	    return;
	  }

	  if (iframeMessage.windowId !== this.windowId) {
	    debug('mismatched window id', iframeMessage.windowId, this.windowId);
	    return;
	  }

	  switch (iframeMessage.type) {
	  case 's':
	    this.iframeObj.loaded();
	    // window global dependency
	    this.postMessage('s', JSON3.stringify([
	      version
	    , this.transport
	    , this.transUrl
	    , this.baseUrl
	    ]));
	    break;
	  case 't':
	    this.emit('message', iframeMessage.data);
	    break;
	  case 'c':
	    var cdata;
	    try {
	      cdata = JSON3.parse(iframeMessage.data);
	    } catch (ignored) {
	      debug('bad json', iframeMessage.data);
	      return;
	    }
	    this.emit('close', cdata[0], cdata[1]);
	    this.close();
	    break;
	  }
	};

	IframeTransport.prototype.postMessage = function(type, data) {
	  debug('postMessage', type, data);
	  this.iframeObj.post(JSON3.stringify({
	    windowId: this.windowId
	  , type: type
	  , data: data || ''
	  }), this.origin);
	};

	IframeTransport.prototype.send = function(message) {
	  debug('send', message);
	  this.postMessage('m', message);
	};

	IframeTransport.enabled = function() {
	  return iframeUtils.iframeEnabled;
	};

	IframeTransport.transportName = 'iframe';
	IframeTransport.roundTrips = 2;

	module.exports = IframeTransport;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 43 */
/***/ function(module, exports, __webpack_require__) {

	var __WEBPACK_AMD_DEFINE_RESULT__;/* WEBPACK VAR INJECTION */(function(module, global) {/*! JSON v3.3.2 | http://bestiejs.github.io/json3 | Copyright 2012-2014, Kit Cambridge | http://kit.mit-license.org */
	;(function () {
	  // Detect the `define` function exposed by asynchronous module loaders. The
	  // strict `define` check is necessary for compatibility with `r.js`.
	  var isLoader = "function" === "function" && __webpack_require__(44);

	  // A set of types used to distinguish objects from primitives.
	  var objectTypes = {
	    "function": true,
	    "object": true
	  };

	  // Detect the `exports` object exposed by CommonJS implementations.
	  var freeExports = objectTypes[typeof exports] && exports && !exports.nodeType && exports;

	  // Use the `global` object exposed by Node (including Browserify via
	  // `insert-module-globals`), Narwhal, and Ringo as the default context,
	  // and the `window` object in browsers. Rhino exports a `global` function
	  // instead.
	  var root = objectTypes[typeof window] && window || this,
	      freeGlobal = freeExports && objectTypes[typeof module] && module && !module.nodeType && typeof global == "object" && global;

	  if (freeGlobal && (freeGlobal["global"] === freeGlobal || freeGlobal["window"] === freeGlobal || freeGlobal["self"] === freeGlobal)) {
	    root = freeGlobal;
	  }

	  // Public: Initializes JSON 3 using the given `context` object, attaching the
	  // `stringify` and `parse` functions to the specified `exports` object.
	  function runInContext(context, exports) {
	    context || (context = root["Object"]());
	    exports || (exports = root["Object"]());

	    // Native constructor aliases.
	    var Number = context["Number"] || root["Number"],
	        String = context["String"] || root["String"],
	        Object = context["Object"] || root["Object"],
	        Date = context["Date"] || root["Date"],
	        SyntaxError = context["SyntaxError"] || root["SyntaxError"],
	        TypeError = context["TypeError"] || root["TypeError"],
	        Math = context["Math"] || root["Math"],
	        nativeJSON = context["JSON"] || root["JSON"];

	    // Delegate to the native `stringify` and `parse` implementations.
	    if (typeof nativeJSON == "object" && nativeJSON) {
	      exports.stringify = nativeJSON.stringify;
	      exports.parse = nativeJSON.parse;
	    }

	    // Convenience aliases.
	    var objectProto = Object.prototype,
	        getClass = objectProto.toString,
	        isProperty, forEach, undef;

	    // Test the `Date#getUTC*` methods. Based on work by @Yaffle.
	    var isExtended = new Date(-3509827334573292);
	    try {
	      // The `getUTCFullYear`, `Month`, and `Date` methods return nonsensical
	      // results for certain dates in Opera >= 10.53.
	      isExtended = isExtended.getUTCFullYear() == -109252 && isExtended.getUTCMonth() === 0 && isExtended.getUTCDate() === 1 &&
	        // Safari < 2.0.2 stores the internal millisecond time value correctly,
	        // but clips the values returned by the date methods to the range of
	        // signed 32-bit integers ([-2 ** 31, 2 ** 31 - 1]).
	        isExtended.getUTCHours() == 10 && isExtended.getUTCMinutes() == 37 && isExtended.getUTCSeconds() == 6 && isExtended.getUTCMilliseconds() == 708;
	    } catch (exception) {}

	    // Internal: Determines whether the native `JSON.stringify` and `parse`
	    // implementations are spec-compliant. Based on work by Ken Snyder.
	    function has(name) {
	      if (has[name] !== undef) {
	        // Return cached feature test result.
	        return has[name];
	      }
	      var isSupported;
	      if (name == "bug-string-char-index") {
	        // IE <= 7 doesn't support accessing string characters using square
	        // bracket notation. IE 8 only supports this for primitives.
	        isSupported = "a"[0] != "a";
	      } else if (name == "json") {
	        // Indicates whether both `JSON.stringify` and `JSON.parse` are
	        // supported.
	        isSupported = has("json-stringify") && has("json-parse");
	      } else {
	        var value, serialized = '{"a":[1,true,false,null,"\\u0000\\b\\n\\f\\r\\t"]}';
	        // Test `JSON.stringify`.
	        if (name == "json-stringify") {
	          var stringify = exports.stringify, stringifySupported = typeof stringify == "function" && isExtended;
	          if (stringifySupported) {
	            // A test function object with a custom `toJSON` method.
	            (value = function () {
	              return 1;
	            }).toJSON = value;
	            try {
	              stringifySupported =
	                // Firefox 3.1b1 and b2 serialize string, number, and boolean
	                // primitives as object literals.
	                stringify(0) === "0" &&
	                // FF 3.1b1, b2, and JSON 2 serialize wrapped primitives as object
	                // literals.
	                stringify(new Number()) === "0" &&
	                stringify(new String()) == '""' &&
	                // FF 3.1b1, 2 throw an error if the value is `null`, `undefined`, or
	                // does not define a canonical JSON representation (this applies to
	                // objects with `toJSON` properties as well, *unless* they are nested
	                // within an object or array).
	                stringify(getClass) === undef &&
	                // IE 8 serializes `undefined` as `"undefined"`. Safari <= 5.1.7 and
	                // FF 3.1b3 pass this test.
	                stringify(undef) === undef &&
	                // Safari <= 5.1.7 and FF 3.1b3 throw `Error`s and `TypeError`s,
	                // respectively, if the value is omitted entirely.
	                stringify() === undef &&
	                // FF 3.1b1, 2 throw an error if the given value is not a number,
	                // string, array, object, Boolean, or `null` literal. This applies to
	                // objects with custom `toJSON` methods as well, unless they are nested
	                // inside object or array literals. YUI 3.0.0b1 ignores custom `toJSON`
	                // methods entirely.
	                stringify(value) === "1" &&
	                stringify([value]) == "[1]" &&
	                // Prototype <= 1.6.1 serializes `[undefined]` as `"[]"` instead of
	                // `"[null]"`.
	                stringify([undef]) == "[null]" &&
	                // YUI 3.0.0b1 fails to serialize `null` literals.
	                stringify(null) == "null" &&
	                // FF 3.1b1, 2 halts serialization if an array contains a function:
	                // `[1, true, getClass, 1]` serializes as "[1,true,],". FF 3.1b3
	                // elides non-JSON values from objects and arrays, unless they
	                // define custom `toJSON` methods.
	                stringify([undef, getClass, null]) == "[null,null,null]" &&
	                // Simple serialization test. FF 3.1b1 uses Unicode escape sequences
	                // where character escape codes are expected (e.g., `\b` => `\u0008`).
	                stringify({ "a": [value, true, false, null, "\x00\b\n\f\r\t"] }) == serialized &&
	                // FF 3.1b1 and b2 ignore the `filter` and `width` arguments.
	                stringify(null, value) === "1" &&
	                stringify([1, 2], null, 1) == "[\n 1,\n 2\n]" &&
	                // JSON 2, Prototype <= 1.7, and older WebKit builds incorrectly
	                // serialize extended years.
	                stringify(new Date(-8.64e15)) == '"-271821-04-20T00:00:00.000Z"' &&
	                // The milliseconds are optional in ES 5, but required in 5.1.
	                stringify(new Date(8.64e15)) == '"+275760-09-13T00:00:00.000Z"' &&
	                // Firefox <= 11.0 incorrectly serializes years prior to 0 as negative
	                // four-digit years instead of six-digit years. Credits: @Yaffle.
	                stringify(new Date(-621987552e5)) == '"-000001-01-01T00:00:00.000Z"' &&
	                // Safari <= 5.1.5 and Opera >= 10.53 incorrectly serialize millisecond
	                // values less than 1000. Credits: @Yaffle.
	                stringify(new Date(-1)) == '"1969-12-31T23:59:59.999Z"';
	            } catch (exception) {
	              stringifySupported = false;
	            }
	          }
	          isSupported = stringifySupported;
	        }
	        // Test `JSON.parse`.
	        if (name == "json-parse") {
	          var parse = exports.parse;
	          if (typeof parse == "function") {
	            try {
	              // FF 3.1b1, b2 will throw an exception if a bare literal is provided.
	              // Conforming implementations should also coerce the initial argument to
	              // a string prior to parsing.
	              if (parse("0") === 0 && !parse(false)) {
	                // Simple parsing test.
	                value = parse(serialized);
	                var parseSupported = value["a"].length == 5 && value["a"][0] === 1;
	                if (parseSupported) {
	                  try {
	                    // Safari <= 5.1.2 and FF 3.1b1 allow unescaped tabs in strings.
	                    parseSupported = !parse('"\t"');
	                  } catch (exception) {}
	                  if (parseSupported) {
	                    try {
	                      // FF 4.0 and 4.0.1 allow leading `+` signs and leading
	                      // decimal points. FF 4.0, 4.0.1, and IE 9-10 also allow
	                      // certain octal literals.
	                      parseSupported = parse("01") !== 1;
	                    } catch (exception) {}
	                  }
	                  if (parseSupported) {
	                    try {
	                      // FF 4.0, 4.0.1, and Rhino 1.7R3-R4 allow trailing decimal
	                      // points. These environments, along with FF 3.1b1 and 2,
	                      // also allow trailing commas in JSON objects and arrays.
	                      parseSupported = parse("1.") !== 1;
	                    } catch (exception) {}
	                  }
	                }
	              }
	            } catch (exception) {
	              parseSupported = false;
	            }
	          }
	          isSupported = parseSupported;
	        }
	      }
	      return has[name] = !!isSupported;
	    }

	    if (!has("json")) {
	      // Common `[[Class]]` name aliases.
	      var functionClass = "[object Function]",
	          dateClass = "[object Date]",
	          numberClass = "[object Number]",
	          stringClass = "[object String]",
	          arrayClass = "[object Array]",
	          booleanClass = "[object Boolean]";

	      // Detect incomplete support for accessing string characters by index.
	      var charIndexBuggy = has("bug-string-char-index");

	      // Define additional utility methods if the `Date` methods are buggy.
	      if (!isExtended) {
	        var floor = Math.floor;
	        // A mapping between the months of the year and the number of days between
	        // January 1st and the first of the respective month.
	        var Months = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
	        // Internal: Calculates the number of days between the Unix epoch and the
	        // first day of the given month.
	        var getDay = function (year, month) {
	          return Months[month] + 365 * (year - 1970) + floor((year - 1969 + (month = +(month > 1))) / 4) - floor((year - 1901 + month) / 100) + floor((year - 1601 + month) / 400);
	        };
	      }

	      // Internal: Determines if a property is a direct property of the given
	      // object. Delegates to the native `Object#hasOwnProperty` method.
	      if (!(isProperty = objectProto.hasOwnProperty)) {
	        isProperty = function (property) {
	          var members = {}, constructor;
	          if ((members.__proto__ = null, members.__proto__ = {
	            // The *proto* property cannot be set multiple times in recent
	            // versions of Firefox and SeaMonkey.
	            "toString": 1
	          }, members).toString != getClass) {
	            // Safari <= 2.0.3 doesn't implement `Object#hasOwnProperty`, but
	            // supports the mutable *proto* property.
	            isProperty = function (property) {
	              // Capture and break the object's prototype chain (see section 8.6.2
	              // of the ES 5.1 spec). The parenthesized expression prevents an
	              // unsafe transformation by the Closure Compiler.
	              var original = this.__proto__, result = property in (this.__proto__ = null, this);
	              // Restore the original prototype chain.
	              this.__proto__ = original;
	              return result;
	            };
	          } else {
	            // Capture a reference to the top-level `Object` constructor.
	            constructor = members.constructor;
	            // Use the `constructor` property to simulate `Object#hasOwnProperty` in
	            // other environments.
	            isProperty = function (property) {
	              var parent = (this.constructor || constructor).prototype;
	              return property in this && !(property in parent && this[property] === parent[property]);
	            };
	          }
	          members = null;
	          return isProperty.call(this, property);
	        };
	      }

	      // Internal: Normalizes the `for...in` iteration algorithm across
	      // environments. Each enumerated key is yielded to a `callback` function.
	      forEach = function (object, callback) {
	        var size = 0, Properties, members, property;

	        // Tests for bugs in the current environment's `for...in` algorithm. The
	        // `valueOf` property inherits the non-enumerable flag from
	        // `Object.prototype` in older versions of IE, Netscape, and Mozilla.
	        (Properties = function () {
	          this.valueOf = 0;
	        }).prototype.valueOf = 0;

	        // Iterate over a new instance of the `Properties` class.
	        members = new Properties();
	        for (property in members) {
	          // Ignore all properties inherited from `Object.prototype`.
	          if (isProperty.call(members, property)) {
	            size++;
	          }
	        }
	        Properties = members = null;

	        // Normalize the iteration algorithm.
	        if (!size) {
	          // A list of non-enumerable properties inherited from `Object.prototype`.
	          members = ["valueOf", "toString", "toLocaleString", "propertyIsEnumerable", "isPrototypeOf", "hasOwnProperty", "constructor"];
	          // IE <= 8, Mozilla 1.0, and Netscape 6.2 ignore shadowed non-enumerable
	          // properties.
	          forEach = function (object, callback) {
	            var isFunction = getClass.call(object) == functionClass, property, length;
	            var hasProperty = !isFunction && typeof object.constructor != "function" && objectTypes[typeof object.hasOwnProperty] && object.hasOwnProperty || isProperty;
	            for (property in object) {
	              // Gecko <= 1.0 enumerates the `prototype` property of functions under
	              // certain conditions; IE does not.
	              if (!(isFunction && property == "prototype") && hasProperty.call(object, property)) {
	                callback(property);
	              }
	            }
	            // Manually invoke the callback for each non-enumerable property.
	            for (length = members.length; property = members[--length]; hasProperty.call(object, property) && callback(property));
	          };
	        } else if (size == 2) {
	          // Safari <= 2.0.4 enumerates shadowed properties twice.
	          forEach = function (object, callback) {
	            // Create a set of iterated properties.
	            var members = {}, isFunction = getClass.call(object) == functionClass, property;
	            for (property in object) {
	              // Store each property name to prevent double enumeration. The
	              // `prototype` property of functions is not enumerated due to cross-
	              // environment inconsistencies.
	              if (!(isFunction && property == "prototype") && !isProperty.call(members, property) && (members[property] = 1) && isProperty.call(object, property)) {
	                callback(property);
	              }
	            }
	          };
	        } else {
	          // No bugs detected; use the standard `for...in` algorithm.
	          forEach = function (object, callback) {
	            var isFunction = getClass.call(object) == functionClass, property, isConstructor;
	            for (property in object) {
	              if (!(isFunction && property == "prototype") && isProperty.call(object, property) && !(isConstructor = property === "constructor")) {
	                callback(property);
	              }
	            }
	            // Manually invoke the callback for the `constructor` property due to
	            // cross-environment inconsistencies.
	            if (isConstructor || isProperty.call(object, (property = "constructor"))) {
	              callback(property);
	            }
	          };
	        }
	        return forEach(object, callback);
	      };

	      // Public: Serializes a JavaScript `value` as a JSON string. The optional
	      // `filter` argument may specify either a function that alters how object and
	      // array members are serialized, or an array of strings and numbers that
	      // indicates which properties should be serialized. The optional `width`
	      // argument may be either a string or number that specifies the indentation
	      // level of the output.
	      if (!has("json-stringify")) {
	        // Internal: A map of control characters and their escaped equivalents.
	        var Escapes = {
	          92: "\\\\",
	          34: '\\"',
	          8: "\\b",
	          12: "\\f",
	          10: "\\n",
	          13: "\\r",
	          9: "\\t"
	        };

	        // Internal: Converts `value` into a zero-padded string such that its
	        // length is at least equal to `width`. The `width` must be <= 6.
	        var leadingZeroes = "000000";
	        var toPaddedString = function (width, value) {
	          // The `|| 0` expression is necessary to work around a bug in
	          // Opera <= 7.54u2 where `0 == -0`, but `String(-0) !== "0"`.
	          return (leadingZeroes + (value || 0)).slice(-width);
	        };

	        // Internal: Double-quotes a string `value`, replacing all ASCII control
	        // characters (characters with code unit values between 0 and 31) with
	        // their escaped equivalents. This is an implementation of the
	        // `Quote(value)` operation defined in ES 5.1 section 15.12.3.
	        var unicodePrefix = "\\u00";
	        var quote = function (value) {
	          var result = '"', index = 0, length = value.length, useCharIndex = !charIndexBuggy || length > 10;
	          var symbols = useCharIndex && (charIndexBuggy ? value.split("") : value);
	          for (; index < length; index++) {
	            var charCode = value.charCodeAt(index);
	            // If the character is a control character, append its Unicode or
	            // shorthand escape sequence; otherwise, append the character as-is.
	            switch (charCode) {
	              case 8: case 9: case 10: case 12: case 13: case 34: case 92:
	                result += Escapes[charCode];
	                break;
	              default:
	                if (charCode < 32) {
	                  result += unicodePrefix + toPaddedString(2, charCode.toString(16));
	                  break;
	                }
	                result += useCharIndex ? symbols[index] : value.charAt(index);
	            }
	          }
	          return result + '"';
	        };

	        // Internal: Recursively serializes an object. Implements the
	        // `Str(key, holder)`, `JO(value)`, and `JA(value)` operations.
	        var serialize = function (property, object, callback, properties, whitespace, indentation, stack) {
	          var value, className, year, month, date, time, hours, minutes, seconds, milliseconds, results, element, index, length, prefix, result;
	          try {
	            // Necessary for host object support.
	            value = object[property];
	          } catch (exception) {}
	          if (typeof value == "object" && value) {
	            className = getClass.call(value);
	            if (className == dateClass && !isProperty.call(value, "toJSON")) {
	              if (value > -1 / 0 && value < 1 / 0) {
	                // Dates are serialized according to the `Date#toJSON` method
	                // specified in ES 5.1 section 15.9.5.44. See section 15.9.1.15
	                // for the ISO 8601 date time string format.
	                if (getDay) {
	                  // Manually compute the year, month, date, hours, minutes,
	                  // seconds, and milliseconds if the `getUTC*` methods are
	                  // buggy. Adapted from @Yaffle's `date-shim` project.
	                  date = floor(value / 864e5);
	                  for (year = floor(date / 365.2425) + 1970 - 1; getDay(year + 1, 0) <= date; year++);
	                  for (month = floor((date - getDay(year, 0)) / 30.42); getDay(year, month + 1) <= date; month++);
	                  date = 1 + date - getDay(year, month);
	                  // The `time` value specifies the time within the day (see ES
	                  // 5.1 section 15.9.1.2). The formula `(A % B + B) % B` is used
	                  // to compute `A modulo B`, as the `%` operator does not
	                  // correspond to the `modulo` operation for negative numbers.
	                  time = (value % 864e5 + 864e5) % 864e5;
	                  // The hours, minutes, seconds, and milliseconds are obtained by
	                  // decomposing the time within the day. See section 15.9.1.10.
	                  hours = floor(time / 36e5) % 24;
	                  minutes = floor(time / 6e4) % 60;
	                  seconds = floor(time / 1e3) % 60;
	                  milliseconds = time % 1e3;
	                } else {
	                  year = value.getUTCFullYear();
	                  month = value.getUTCMonth();
	                  date = value.getUTCDate();
	                  hours = value.getUTCHours();
	                  minutes = value.getUTCMinutes();
	                  seconds = value.getUTCSeconds();
	                  milliseconds = value.getUTCMilliseconds();
	                }
	                // Serialize extended years correctly.
	                value = (year <= 0 || year >= 1e4 ? (year < 0 ? "-" : "+") + toPaddedString(6, year < 0 ? -year : year) : toPaddedString(4, year)) +
	                  "-" + toPaddedString(2, month + 1) + "-" + toPaddedString(2, date) +
	                  // Months, dates, hours, minutes, and seconds should have two
	                  // digits; milliseconds should have three.
	                  "T" + toPaddedString(2, hours) + ":" + toPaddedString(2, minutes) + ":" + toPaddedString(2, seconds) +
	                  // Milliseconds are optional in ES 5.0, but required in 5.1.
	                  "." + toPaddedString(3, milliseconds) + "Z";
	              } else {
	                value = null;
	              }
	            } else if (typeof value.toJSON == "function" && ((className != numberClass && className != stringClass && className != arrayClass) || isProperty.call(value, "toJSON"))) {
	              // Prototype <= 1.6.1 adds non-standard `toJSON` methods to the
	              // `Number`, `String`, `Date`, and `Array` prototypes. JSON 3
	              // ignores all `toJSON` methods on these objects unless they are
	              // defined directly on an instance.
	              value = value.toJSON(property);
	            }
	          }
	          if (callback) {
	            // If a replacement function was provided, call it to obtain the value
	            // for serialization.
	            value = callback.call(object, property, value);
	          }
	          if (value === null) {
	            return "null";
	          }
	          className = getClass.call(value);
	          if (className == booleanClass) {
	            // Booleans are represented literally.
	            return "" + value;
	          } else if (className == numberClass) {
	            // JSON numbers must be finite. `Infinity` and `NaN` are serialized as
	            // `"null"`.
	            return value > -1 / 0 && value < 1 / 0 ? "" + value : "null";
	          } else if (className == stringClass) {
	            // Strings are double-quoted and escaped.
	            return quote("" + value);
	          }
	          // Recursively serialize objects and arrays.
	          if (typeof value == "object") {
	            // Check for cyclic structures. This is a linear search; performance
	            // is inversely proportional to the number of unique nested objects.
	            for (length = stack.length; length--;) {
	              if (stack[length] === value) {
	                // Cyclic structures cannot be serialized by `JSON.stringify`.
	                throw TypeError();
	              }
	            }
	            // Add the object to the stack of traversed objects.
	            stack.push(value);
	            results = [];
	            // Save the current indentation level and indent one additional level.
	            prefix = indentation;
	            indentation += whitespace;
	            if (className == arrayClass) {
	              // Recursively serialize array elements.
	              for (index = 0, length = value.length; index < length; index++) {
	                element = serialize(index, value, callback, properties, whitespace, indentation, stack);
	                results.push(element === undef ? "null" : element);
	              }
	              result = results.length ? (whitespace ? "[\n" + indentation + results.join(",\n" + indentation) + "\n" + prefix + "]" : ("[" + results.join(",") + "]")) : "[]";
	            } else {
	              // Recursively serialize object members. Members are selected from
	              // either a user-specified list of property names, or the object
	              // itself.
	              forEach(properties || value, function (property) {
	                var element = serialize(property, value, callback, properties, whitespace, indentation, stack);
	                if (element !== undef) {
	                  // According to ES 5.1 section 15.12.3: "If `gap` {whitespace}
	                  // is not the empty string, let `member` {quote(property) + ":"}
	                  // be the concatenation of `member` and the `space` character."
	                  // The "`space` character" refers to the literal space
	                  // character, not the `space` {width} argument provided to
	                  // `JSON.stringify`.
	                  results.push(quote(property) + ":" + (whitespace ? " " : "") + element);
	                }
	              });
	              result = results.length ? (whitespace ? "{\n" + indentation + results.join(",\n" + indentation) + "\n" + prefix + "}" : ("{" + results.join(",") + "}")) : "{}";
	            }
	            // Remove the object from the traversed object stack.
	            stack.pop();
	            return result;
	          }
	        };

	        // Public: `JSON.stringify`. See ES 5.1 section 15.12.3.
	        exports.stringify = function (source, filter, width) {
	          var whitespace, callback, properties, className;
	          if (objectTypes[typeof filter] && filter) {
	            if ((className = getClass.call(filter)) == functionClass) {
	              callback = filter;
	            } else if (className == arrayClass) {
	              // Convert the property names array into a makeshift set.
	              properties = {};
	              for (var index = 0, length = filter.length, value; index < length; value = filter[index++], ((className = getClass.call(value)), className == stringClass || className == numberClass) && (properties[value] = 1));
	            }
	          }
	          if (width) {
	            if ((className = getClass.call(width)) == numberClass) {
	              // Convert the `width` to an integer and create a string containing
	              // `width` number of space characters.
	              if ((width -= width % 1) > 0) {
	                for (whitespace = "", width > 10 && (width = 10); whitespace.length < width; whitespace += " ");
	              }
	            } else if (className == stringClass) {
	              whitespace = width.length <= 10 ? width : width.slice(0, 10);
	            }
	          }
	          // Opera <= 7.54u2 discards the values associated with empty string keys
	          // (`""`) only if they are used directly within an object member list
	          // (e.g., `!("" in { "": 1})`).
	          return serialize("", (value = {}, value[""] = source, value), callback, properties, whitespace, "", []);
	        };
	      }

	      // Public: Parses a JSON source string.
	      if (!has("json-parse")) {
	        var fromCharCode = String.fromCharCode;

	        // Internal: A map of escaped control characters and their unescaped
	        // equivalents.
	        var Unescapes = {
	          92: "\\",
	          34: '"',
	          47: "/",
	          98: "\b",
	          116: "\t",
	          110: "\n",
	          102: "\f",
	          114: "\r"
	        };

	        // Internal: Stores the parser state.
	        var Index, Source;

	        // Internal: Resets the parser state and throws a `SyntaxError`.
	        var abort = function () {
	          Index = Source = null;
	          throw SyntaxError();
	        };

	        // Internal: Returns the next token, or `"$"` if the parser has reached
	        // the end of the source string. A token may be a string, number, `null`
	        // literal, or Boolean literal.
	        var lex = function () {
	          var source = Source, length = source.length, value, begin, position, isSigned, charCode;
	          while (Index < length) {
	            charCode = source.charCodeAt(Index);
	            switch (charCode) {
	              case 9: case 10: case 13: case 32:
	                // Skip whitespace tokens, including tabs, carriage returns, line
	                // feeds, and space characters.
	                Index++;
	                break;
	              case 123: case 125: case 91: case 93: case 58: case 44:
	                // Parse a punctuator token (`{`, `}`, `[`, `]`, `:`, or `,`) at
	                // the current position.
	                value = charIndexBuggy ? source.charAt(Index) : source[Index];
	                Index++;
	                return value;
	              case 34:
	                // `"` delimits a JSON string; advance to the next character and
	                // begin parsing the string. String tokens are prefixed with the
	                // sentinel `@` character to distinguish them from punctuators and
	                // end-of-string tokens.
	                for (value = "@", Index++; Index < length;) {
	                  charCode = source.charCodeAt(Index);
	                  if (charCode < 32) {
	                    // Unescaped ASCII control characters (those with a code unit
	                    // less than the space character) are not permitted.
	                    abort();
	                  } else if (charCode == 92) {
	                    // A reverse solidus (`\`) marks the beginning of an escaped
	                    // control character (including `"`, `\`, and `/`) or Unicode
	                    // escape sequence.
	                    charCode = source.charCodeAt(++Index);
	                    switch (charCode) {
	                      case 92: case 34: case 47: case 98: case 116: case 110: case 102: case 114:
	                        // Revive escaped control characters.
	                        value += Unescapes[charCode];
	                        Index++;
	                        break;
	                      case 117:
	                        // `\u` marks the beginning of a Unicode escape sequence.
	                        // Advance to the first character and validate the
	                        // four-digit code point.
	                        begin = ++Index;
	                        for (position = Index + 4; Index < position; Index++) {
	                          charCode = source.charCodeAt(Index);
	                          // A valid sequence comprises four hexdigits (case-
	                          // insensitive) that form a single hexadecimal value.
	                          if (!(charCode >= 48 && charCode <= 57 || charCode >= 97 && charCode <= 102 || charCode >= 65 && charCode <= 70)) {
	                            // Invalid Unicode escape sequence.
	                            abort();
	                          }
	                        }
	                        // Revive the escaped character.
	                        value += fromCharCode("0x" + source.slice(begin, Index));
	                        break;
	                      default:
	                        // Invalid escape sequence.
	                        abort();
	                    }
	                  } else {
	                    if (charCode == 34) {
	                      // An unescaped double-quote character marks the end of the
	                      // string.
	                      break;
	                    }
	                    charCode = source.charCodeAt(Index);
	                    begin = Index;
	                    // Optimize for the common case where a string is valid.
	                    while (charCode >= 32 && charCode != 92 && charCode != 34) {
	                      charCode = source.charCodeAt(++Index);
	                    }
	                    // Append the string as-is.
	                    value += source.slice(begin, Index);
	                  }
	                }
	                if (source.charCodeAt(Index) == 34) {
	                  // Advance to the next character and return the revived string.
	                  Index++;
	                  return value;
	                }
	                // Unterminated string.
	                abort();
	              default:
	                // Parse numbers and literals.
	                begin = Index;
	                // Advance past the negative sign, if one is specified.
	                if (charCode == 45) {
	                  isSigned = true;
	                  charCode = source.charCodeAt(++Index);
	                }
	                // Parse an integer or floating-point value.
	                if (charCode >= 48 && charCode <= 57) {
	                  // Leading zeroes are interpreted as octal literals.
	                  if (charCode == 48 && ((charCode = source.charCodeAt(Index + 1)), charCode >= 48 && charCode <= 57)) {
	                    // Illegal octal literal.
	                    abort();
	                  }
	                  isSigned = false;
	                  // Parse the integer component.
	                  for (; Index < length && ((charCode = source.charCodeAt(Index)), charCode >= 48 && charCode <= 57); Index++);
	                  // Floats cannot contain a leading decimal point; however, this
	                  // case is already accounted for by the parser.
	                  if (source.charCodeAt(Index) == 46) {
	                    position = ++Index;
	                    // Parse the decimal component.
	                    for (; position < length && ((charCode = source.charCodeAt(position)), charCode >= 48 && charCode <= 57); position++);
	                    if (position == Index) {
	                      // Illegal trailing decimal.
	                      abort();
	                    }
	                    Index = position;
	                  }
	                  // Parse exponents. The `e` denoting the exponent is
	                  // case-insensitive.
	                  charCode = source.charCodeAt(Index);
	                  if (charCode == 101 || charCode == 69) {
	                    charCode = source.charCodeAt(++Index);
	                    // Skip past the sign following the exponent, if one is
	                    // specified.
	                    if (charCode == 43 || charCode == 45) {
	                      Index++;
	                    }
	                    // Parse the exponential component.
	                    for (position = Index; position < length && ((charCode = source.charCodeAt(position)), charCode >= 48 && charCode <= 57); position++);
	                    if (position == Index) {
	                      // Illegal empty exponent.
	                      abort();
	                    }
	                    Index = position;
	                  }
	                  // Coerce the parsed value to a JavaScript number.
	                  return +source.slice(begin, Index);
	                }
	                // A negative sign may only precede numbers.
	                if (isSigned) {
	                  abort();
	                }
	                // `true`, `false`, and `null` literals.
	                if (source.slice(Index, Index + 4) == "true") {
	                  Index += 4;
	                  return true;
	                } else if (source.slice(Index, Index + 5) == "false") {
	                  Index += 5;
	                  return false;
	                } else if (source.slice(Index, Index + 4) == "null") {
	                  Index += 4;
	                  return null;
	                }
	                // Unrecognized token.
	                abort();
	            }
	          }
	          // Return the sentinel `$` character if the parser has reached the end
	          // of the source string.
	          return "$";
	        };

	        // Internal: Parses a JSON `value` token.
	        var get = function (value) {
	          var results, hasMembers;
	          if (value == "$") {
	            // Unexpected end of input.
	            abort();
	          }
	          if (typeof value == "string") {
	            if ((charIndexBuggy ? value.charAt(0) : value[0]) == "@") {
	              // Remove the sentinel `@` character.
	              return value.slice(1);
	            }
	            // Parse object and array literals.
	            if (value == "[") {
	              // Parses a JSON array, returning a new JavaScript array.
	              results = [];
	              for (;; hasMembers || (hasMembers = true)) {
	                value = lex();
	                // A closing square bracket marks the end of the array literal.
	                if (value == "]") {
	                  break;
	                }
	                // If the array literal contains elements, the current token
	                // should be a comma separating the previous element from the
	                // next.
	                if (hasMembers) {
	                  if (value == ",") {
	                    value = lex();
	                    if (value == "]") {
	                      // Unexpected trailing `,` in array literal.
	                      abort();
	                    }
	                  } else {
	                    // A `,` must separate each array element.
	                    abort();
	                  }
	                }
	                // Elisions and leading commas are not permitted.
	                if (value == ",") {
	                  abort();
	                }
	                results.push(get(value));
	              }
	              return results;
	            } else if (value == "{") {
	              // Parses a JSON object, returning a new JavaScript object.
	              results = {};
	              for (;; hasMembers || (hasMembers = true)) {
	                value = lex();
	                // A closing curly brace marks the end of the object literal.
	                if (value == "}") {
	                  break;
	                }
	                // If the object literal contains members, the current token
	                // should be a comma separator.
	                if (hasMembers) {
	                  if (value == ",") {
	                    value = lex();
	                    if (value == "}") {
	                      // Unexpected trailing `,` in object literal.
	                      abort();
	                    }
	                  } else {
	                    // A `,` must separate each object member.
	                    abort();
	                  }
	                }
	                // Leading commas are not permitted, object property names must be
	                // double-quoted strings, and a `:` must separate each property
	                // name and value.
	                if (value == "," || typeof value != "string" || (charIndexBuggy ? value.charAt(0) : value[0]) != "@" || lex() != ":") {
	                  abort();
	                }
	                results[value.slice(1)] = get(lex());
	              }
	              return results;
	            }
	            // Unexpected token encountered.
	            abort();
	          }
	          return value;
	        };

	        // Internal: Updates a traversed object member.
	        var update = function (source, property, callback) {
	          var element = walk(source, property, callback);
	          if (element === undef) {
	            delete source[property];
	          } else {
	            source[property] = element;
	          }
	        };

	        // Internal: Recursively traverses a parsed JSON object, invoking the
	        // `callback` function for each value. This is an implementation of the
	        // `Walk(holder, name)` operation defined in ES 5.1 section 15.12.2.
	        var walk = function (source, property, callback) {
	          var value = source[property], length;
	          if (typeof value == "object" && value) {
	            // `forEach` can't be used to traverse an array in Opera <= 8.54
	            // because its `Object#hasOwnProperty` implementation returns `false`
	            // for array indices (e.g., `![1, 2, 3].hasOwnProperty("0")`).
	            if (getClass.call(value) == arrayClass) {
	              for (length = value.length; length--;) {
	                update(value, length, callback);
	              }
	            } else {
	              forEach(value, function (property) {
	                update(value, property, callback);
	              });
	            }
	          }
	          return callback.call(source, property, value);
	        };

	        // Public: `JSON.parse`. See ES 5.1 section 15.12.2.
	        exports.parse = function (source, callback) {
	          var result, value;
	          Index = 0;
	          Source = "" + source;
	          result = get(lex());
	          // If a JSON string contains multiple tokens, it is invalid.
	          if (lex() != "$") {
	            abort();
	          }
	          // Reset the parser state.
	          Index = Source = null;
	          return callback && getClass.call(callback) == functionClass ? walk((value = {}, value[""] = result, value), "", callback) : result;
	        };
	      }
	    }

	    exports["runInContext"] = runInContext;
	    return exports;
	  }

	  if (freeExports && !isLoader) {
	    // Export for CommonJS environments.
	    runInContext(root, freeExports);
	  } else {
	    // Export for web browsers and JavaScript engines.
	    var nativeJSON = root.JSON,
	        previousJSON = root["JSON3"],
	        isRestored = false;

	    var JSON3 = runInContext(root, (root["JSON3"] = {
	      // Public: Restores the original value of the global `JSON` object and
	      // returns a reference to the `JSON3` object.
	      "noConflict": function () {
	        if (!isRestored) {
	          isRestored = true;
	          root.JSON = nativeJSON;
	          root["JSON3"] = previousJSON;
	          nativeJSON = previousJSON = null;
	        }
	        return JSON3;
	      }
	    }));

	    root.JSON = {
	      "parse": JSON3.parse,
	      "stringify": JSON3.stringify
	    };
	  }

	  // Export for asynchronous module loaders.
	  if (isLoader) {
	    !(__WEBPACK_AMD_DEFINE_RESULT__ = function () {
	      return JSON3;
	    }.call(exports, __webpack_require__, exports, module), __WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
	  }
	}).call(this);

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(4)(module), (function() { return this; }())))

/***/ },
/* 44 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(__webpack_amd_options__) {module.exports = __webpack_amd_options__;

	/* WEBPACK VAR INJECTION */}.call(exports, {}))

/***/ },
/* 45 */
/***/ function(module, exports) {

	module.exports = '1.1.5';


/***/ },
/* 46 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	var eventUtils = __webpack_require__(12)
	  , JSON3 = __webpack_require__(43)
	  , browser = __webpack_require__(35)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:utils:iframe');
	}

	module.exports = {
	  WPrefix: '_jp'
	, currentWindowId: null

	, polluteGlobalNamespace: function() {
	    if (!(module.exports.WPrefix in global)) {
	      global[module.exports.WPrefix] = {};
	    }
	  }

	, postMessage: function(type, data) {
	    if (global.parent !== global) {
	      global.parent.postMessage(JSON3.stringify({
	        windowId: module.exports.currentWindowId
	      , type: type
	      , data: data || ''
	      }), '*');
	    } else {
	      debug('Cannot postMessage, no parent window.', type, data);
	    }
	  }

	, createIframe: function(iframeUrl, errorCallback) {
	    var iframe = global.document.createElement('iframe');
	    var tref, unloadRef;
	    var unattach = function() {
	      debug('unattach');
	      clearTimeout(tref);
	      // Explorer had problems with that.
	      try {
	        iframe.onload = null;
	      } catch (x) {
	        // intentionally empty
	      }
	      iframe.onerror = null;
	    };
	    var cleanup = function() {
	      debug('cleanup');
	      if (iframe) {
	        unattach();
	        // This timeout makes chrome fire onbeforeunload event
	        // within iframe. Without the timeout it goes straight to
	        // onunload.
	        setTimeout(function() {
	          if (iframe) {
	            iframe.parentNode.removeChild(iframe);
	          }
	          iframe = null;
	        }, 0);
	        eventUtils.unloadDel(unloadRef);
	      }
	    };
	    var onerror = function(err) {
	      debug('onerror', err);
	      if (iframe) {
	        cleanup();
	        errorCallback(err);
	      }
	    };
	    var post = function(msg, origin) {
	      debug('post', msg, origin);
	      setTimeout(function() {
	        try {
	          // When the iframe is not loaded, IE raises an exception
	          // on 'contentWindow'.
	          if (iframe && iframe.contentWindow) {
	            iframe.contentWindow.postMessage(msg, origin);
	          }
	        } catch (x) {
	          // intentionally empty
	        }
	      }, 0);
	    };

	    iframe.src = iframeUrl;
	    iframe.style.display = 'none';
	    iframe.style.position = 'absolute';
	    iframe.onerror = function() {
	      onerror('onerror');
	    };
	    iframe.onload = function() {
	      debug('onload');
	      // `onload` is triggered before scripts on the iframe are
	      // executed. Give it few seconds to actually load stuff.
	      clearTimeout(tref);
	      tref = setTimeout(function() {
	        onerror('onload timeout');
	      }, 2000);
	    };
	    global.document.body.appendChild(iframe);
	    tref = setTimeout(function() {
	      onerror('timeout');
	    }, 15000);
	    unloadRef = eventUtils.unloadAdd(cleanup);
	    return {
	      post: post
	    , cleanup: cleanup
	    , loaded: unattach
	    };
	  }

	/* eslint no-undef: "off", new-cap: "off" */
	, createHtmlfile: function(iframeUrl, errorCallback) {
	    var axo = ['Active'].concat('Object').join('X');
	    var doc = new global[axo]('htmlfile');
	    var tref, unloadRef;
	    var iframe;
	    var unattach = function() {
	      clearTimeout(tref);
	      iframe.onerror = null;
	    };
	    var cleanup = function() {
	      if (doc) {
	        unattach();
	        eventUtils.unloadDel(unloadRef);
	        iframe.parentNode.removeChild(iframe);
	        iframe = doc = null;
	        CollectGarbage();
	      }
	    };
	    var onerror = function(r) {
	      debug('onerror', r);
	      if (doc) {
	        cleanup();
	        errorCallback(r);
	      }
	    };
	    var post = function(msg, origin) {
	      try {
	        // When the iframe is not loaded, IE raises an exception
	        // on 'contentWindow'.
	        setTimeout(function() {
	          if (iframe && iframe.contentWindow) {
	              iframe.contentWindow.postMessage(msg, origin);
	          }
	        }, 0);
	      } catch (x) {
	        // intentionally empty
	      }
	    };

	    doc.open();
	    doc.write('<html><s' + 'cript>' +
	              'document.domain="' + global.document.domain + '";' +
	              '</s' + 'cript></html>');
	    doc.close();
	    doc.parentWindow[module.exports.WPrefix] = global[module.exports.WPrefix];
	    var c = doc.createElement('div');
	    doc.body.appendChild(c);
	    iframe = doc.createElement('iframe');
	    c.appendChild(iframe);
	    iframe.src = iframeUrl;
	    iframe.onerror = function() {
	      onerror('onerror');
	    };
	    tref = setTimeout(function() {
	      onerror('timeout');
	    }, 15000);
	    unloadRef = eventUtils.unloadAdd(cleanup);
	    return {
	      post: post
	    , cleanup: cleanup
	    , loaded: unattach
	    };
	  }
	};

	module.exports.iframeEnabled = false;
	if (global.document) {
	  // postMessage misbehaves in konqueror 4.6.5 - the messages are delivered with
	  // huge delay, or not at all.
	  module.exports.iframeEnabled = (typeof global.postMessage === 'function' ||
	    typeof global.postMessage === 'object') && (!browser.isKonqueror());
	}

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 47 */
/***/ function(module, exports) {

	'use strict';

	module.exports = {
	  isObject: function(obj) {
	    var type = typeof obj;
	    return type === 'function' || type === 'object' && !!obj;
	  }

	, extend: function(obj) {
	    if (!this.isObject(obj)) {
	      return obj;
	    }
	    var source, prop;
	    for (var i = 1, length = arguments.length; i < length; i++) {
	      source = arguments[i];
	      for (prop in source) {
	        if (Object.prototype.hasOwnProperty.call(source, prop)) {
	          obj[prop] = source[prop];
	        }
	      }
	    }
	    return obj;
	  }
	};


/***/ },
/* 48 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , HtmlfileReceiver = __webpack_require__(49)
	  , XHRLocalObject = __webpack_require__(34)
	  , AjaxBasedTransport = __webpack_require__(27)
	  ;

	function HtmlFileTransport(transUrl) {
	  if (!HtmlfileReceiver.enabled) {
	    throw new Error('Transport created when disabled');
	  }
	  AjaxBasedTransport.call(this, transUrl, '/htmlfile', HtmlfileReceiver, XHRLocalObject);
	}

	inherits(HtmlFileTransport, AjaxBasedTransport);

	HtmlFileTransport.enabled = function(info) {
	  return HtmlfileReceiver.enabled && info.sameOrigin;
	};

	HtmlFileTransport.transportName = 'htmlfile';
	HtmlFileTransport.roundTrips = 2;

	module.exports = HtmlFileTransport;


/***/ },
/* 49 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	var inherits = __webpack_require__(22)
	  , iframeUtils = __webpack_require__(46)
	  , urlUtils = __webpack_require__(15)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  , random = __webpack_require__(13)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:receiver:htmlfile');
	}

	function HtmlfileReceiver(url) {
	  debug(url);
	  EventEmitter.call(this);
	  var self = this;
	  iframeUtils.polluteGlobalNamespace();

	  this.id = 'a' + random.string(6);
	  url = urlUtils.addQuery(url, 'c=' + decodeURIComponent(iframeUtils.WPrefix + '.' + this.id));

	  debug('using htmlfile', HtmlfileReceiver.htmlfileEnabled);
	  var constructFunc = HtmlfileReceiver.htmlfileEnabled ?
	      iframeUtils.createHtmlfile : iframeUtils.createIframe;

	  global[iframeUtils.WPrefix][this.id] = {
	    start: function() {
	      debug('start');
	      self.iframeObj.loaded();
	    }
	  , message: function(data) {
	      debug('message', data);
	      self.emit('message', data);
	    }
	  , stop: function() {
	      debug('stop');
	      self._cleanup();
	      self._close('network');
	    }
	  };
	  this.iframeObj = constructFunc(url, function() {
	    debug('callback');
	    self._cleanup();
	    self._close('permanent');
	  });
	}

	inherits(HtmlfileReceiver, EventEmitter);

	HtmlfileReceiver.prototype.abort = function() {
	  debug('abort');
	  this._cleanup();
	  this._close('user');
	};

	HtmlfileReceiver.prototype._cleanup = function() {
	  debug('_cleanup');
	  if (this.iframeObj) {
	    this.iframeObj.cleanup();
	    this.iframeObj = null;
	  }
	  delete global[iframeUtils.WPrefix][this.id];
	};

	HtmlfileReceiver.prototype._close = function(reason) {
	  debug('_close', reason);
	  this.emit('close', null, reason);
	  this.removeAllListeners();
	};

	HtmlfileReceiver.htmlfileEnabled = false;

	// obfuscate to avoid firewalls
	var axo = ['Active'].concat('Object').join('X');
	if (axo in global) {
	  try {
	    HtmlfileReceiver.htmlfileEnabled = !!new global[axo]('htmlfile');
	  } catch (x) {
	    // intentionally empty
	  }
	}

	HtmlfileReceiver.enabled = HtmlfileReceiver.htmlfileEnabled || iframeUtils.iframeEnabled;

	module.exports = HtmlfileReceiver;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 50 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , AjaxBasedTransport = __webpack_require__(27)
	  , XhrReceiver = __webpack_require__(31)
	  , XHRCorsObject = __webpack_require__(32)
	  , XHRLocalObject = __webpack_require__(34)
	  ;

	function XhrPollingTransport(transUrl) {
	  if (!XHRLocalObject.enabled && !XHRCorsObject.enabled) {
	    throw new Error('Transport created when disabled');
	  }
	  AjaxBasedTransport.call(this, transUrl, '/xhr', XhrReceiver, XHRCorsObject);
	}

	inherits(XhrPollingTransport, AjaxBasedTransport);

	XhrPollingTransport.enabled = function(info) {
	  if (info.nullOrigin) {
	    return false;
	  }

	  if (XHRLocalObject.enabled && info.sameOrigin) {
	    return true;
	  }
	  return XHRCorsObject.enabled;
	};

	XhrPollingTransport.transportName = 'xhr-polling';
	XhrPollingTransport.roundTrips = 2; // preflight, ajax

	module.exports = XhrPollingTransport;


/***/ },
/* 51 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , AjaxBasedTransport = __webpack_require__(27)
	  , XdrStreamingTransport = __webpack_require__(36)
	  , XhrReceiver = __webpack_require__(31)
	  , XDRObject = __webpack_require__(37)
	  ;

	function XdrPollingTransport(transUrl) {
	  if (!XDRObject.enabled) {
	    throw new Error('Transport created when disabled');
	  }
	  AjaxBasedTransport.call(this, transUrl, '/xhr', XhrReceiver, XDRObject);
	}

	inherits(XdrPollingTransport, AjaxBasedTransport);

	XdrPollingTransport.enabled = XdrStreamingTransport.enabled;
	XdrPollingTransport.transportName = 'xdr-polling';
	XdrPollingTransport.roundTrips = 2; // preflight, ajax

	module.exports = XdrPollingTransport;


/***/ },
/* 52 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	// The simplest and most robust transport, using the well-know cross
	// domain hack - JSONP. This transport is quite inefficient - one
	// message could use up to one http request. But at least it works almost
	// everywhere.
	// Known limitations:
	//   o you will get a spinning cursor
	//   o for Konqueror a dumb timer is needed to detect errors

	var inherits = __webpack_require__(22)
	  , SenderReceiver = __webpack_require__(28)
	  , JsonpReceiver = __webpack_require__(53)
	  , jsonpSender = __webpack_require__(54)
	  ;

	function JsonPTransport(transUrl) {
	  if (!JsonPTransport.enabled()) {
	    throw new Error('Transport created when disabled');
	  }
	  SenderReceiver.call(this, transUrl, '/jsonp', jsonpSender, JsonpReceiver);
	}

	inherits(JsonPTransport, SenderReceiver);

	JsonPTransport.enabled = function() {
	  return !!global.document;
	};

	JsonPTransport.transportName = 'jsonp-polling';
	JsonPTransport.roundTrips = 1;
	JsonPTransport.needBody = true;

	module.exports = JsonPTransport;

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 53 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	var utils = __webpack_require__(46)
	  , random = __webpack_require__(13)
	  , browser = __webpack_require__(35)
	  , urlUtils = __webpack_require__(15)
	  , inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:receiver:jsonp');
	}

	function JsonpReceiver(url) {
	  debug(url);
	  var self = this;
	  EventEmitter.call(this);

	  utils.polluteGlobalNamespace();

	  this.id = 'a' + random.string(6);
	  var urlWithId = urlUtils.addQuery(url, 'c=' + encodeURIComponent(utils.WPrefix + '.' + this.id));

	  global[utils.WPrefix][this.id] = this._callback.bind(this);
	  this._createScript(urlWithId);

	  // Fallback mostly for Konqueror - stupid timer, 35 seconds shall be plenty.
	  this.timeoutId = setTimeout(function() {
	    debug('timeout');
	    self._abort(new Error('JSONP script loaded abnormally (timeout)'));
	  }, JsonpReceiver.timeout);
	}

	inherits(JsonpReceiver, EventEmitter);

	JsonpReceiver.prototype.abort = function() {
	  debug('abort');
	  if (global[utils.WPrefix][this.id]) {
	    var err = new Error('JSONP user aborted read');
	    err.code = 1000;
	    this._abort(err);
	  }
	};

	JsonpReceiver.timeout = 35000;
	JsonpReceiver.scriptErrorTimeout = 1000;

	JsonpReceiver.prototype._callback = function(data) {
	  debug('_callback', data);
	  this._cleanup();

	  if (this.aborting) {
	    return;
	  }

	  if (data) {
	    debug('message', data);
	    this.emit('message', data);
	  }
	  this.emit('close', null, 'network');
	  this.removeAllListeners();
	};

	JsonpReceiver.prototype._abort = function(err) {
	  debug('_abort', err);
	  this._cleanup();
	  this.aborting = true;
	  this.emit('close', err.code, err.message);
	  this.removeAllListeners();
	};

	JsonpReceiver.prototype._cleanup = function() {
	  debug('_cleanup');
	  clearTimeout(this.timeoutId);
	  if (this.script2) {
	    this.script2.parentNode.removeChild(this.script2);
	    this.script2 = null;
	  }
	  if (this.script) {
	    var script = this.script;
	    // Unfortunately, you can't really abort script loading of
	    // the script.
	    script.parentNode.removeChild(script);
	    script.onreadystatechange = script.onerror =
	        script.onload = script.onclick = null;
	    this.script = null;
	  }
	  delete global[utils.WPrefix][this.id];
	};

	JsonpReceiver.prototype._scriptError = function() {
	  debug('_scriptError');
	  var self = this;
	  if (this.errorTimer) {
	    return;
	  }

	  this.errorTimer = setTimeout(function() {
	    if (!self.loadedOkay) {
	      self._abort(new Error('JSONP script loaded abnormally (onerror)'));
	    }
	  }, JsonpReceiver.scriptErrorTimeout);
	};

	JsonpReceiver.prototype._createScript = function(url) {
	  debug('_createScript', url);
	  var self = this;
	  var script = this.script = global.document.createElement('script');
	  var script2;  // Opera synchronous load trick.

	  script.id = 'a' + random.string(8);
	  script.src = url;
	  script.type = 'text/javascript';
	  script.charset = 'UTF-8';
	  script.onerror = this._scriptError.bind(this);
	  script.onload = function() {
	    debug('onload');
	    self._abort(new Error('JSONP script loaded abnormally (onload)'));
	  };

	  // IE9 fires 'error' event after onreadystatechange or before, in random order.
	  // Use loadedOkay to determine if actually errored
	  script.onreadystatechange = function() {
	    debug('onreadystatechange', script.readyState);
	    if (/loaded|closed/.test(script.readyState)) {
	      if (script && script.htmlFor && script.onclick) {
	        self.loadedOkay = true;
	        try {
	          // In IE, actually execute the script.
	          script.onclick();
	        } catch (x) {
	          // intentionally empty
	        }
	      }
	      if (script) {
	        self._abort(new Error('JSONP script loaded abnormally (onreadystatechange)'));
	      }
	    }
	  };
	  // IE: event/htmlFor/onclick trick.
	  // One can't rely on proper order for onreadystatechange. In order to
	  // make sure, set a 'htmlFor' and 'event' properties, so that
	  // script code will be installed as 'onclick' handler for the
	  // script object. Later, onreadystatechange, manually execute this
	  // code. FF and Chrome doesn't work with 'event' and 'htmlFor'
	  // set. For reference see:
	  //   http://jaubourg.net/2010/07/loading-script-as-onclick-handler-of.html
	  // Also, read on that about script ordering:
	  //   http://wiki.whatwg.org/wiki/Dynamic_Script_Execution_Order
	  if (typeof script.async === 'undefined' && global.document.attachEvent) {
	    // According to mozilla docs, in recent browsers script.async defaults
	    // to 'true', so we may use it to detect a good browser:
	    // https://developer.mozilla.org/en/HTML/Element/script
	    if (!browser.isOpera()) {
	      // Naively assume we're in IE
	      try {
	        script.htmlFor = script.id;
	        script.event = 'onclick';
	      } catch (x) {
	        // intentionally empty
	      }
	      script.async = true;
	    } else {
	      // Opera, second sync script hack
	      script2 = this.script2 = global.document.createElement('script');
	      script2.text = "try{var a = document.getElementById('" + script.id + "'); if(a)a.onerror();}catch(x){};";
	      script.async = script2.async = false;
	    }
	  }
	  if (typeof script.async !== 'undefined') {
	    script.async = true;
	  }

	  var head = global.document.getElementsByTagName('head')[0];
	  head.insertBefore(script, head.firstChild);
	  if (script2) {
	    head.insertBefore(script2, head.firstChild);
	  }
	};

	module.exports = JsonpReceiver;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 54 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	var random = __webpack_require__(13)
	  , urlUtils = __webpack_require__(15)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:sender:jsonp');
	}

	var form, area;

	function createIframe(id) {
	  debug('createIframe', id);
	  try {
	    // ie6 dynamic iframes with target="" support (thanks Chris Lambacher)
	    return global.document.createElement('<iframe name="' + id + '">');
	  } catch (x) {
	    var iframe = global.document.createElement('iframe');
	    iframe.name = id;
	    return iframe;
	  }
	}

	function createForm() {
	  debug('createForm');
	  form = global.document.createElement('form');
	  form.style.display = 'none';
	  form.style.position = 'absolute';
	  form.method = 'POST';
	  form.enctype = 'application/x-www-form-urlencoded';
	  form.acceptCharset = 'UTF-8';

	  area = global.document.createElement('textarea');
	  area.name = 'd';
	  form.appendChild(area);

	  global.document.body.appendChild(form);
	}

	module.exports = function(url, payload, callback) {
	  debug(url, payload);
	  if (!form) {
	    createForm();
	  }
	  var id = 'a' + random.string(8);
	  form.target = id;
	  form.action = urlUtils.addQuery(urlUtils.addPath(url, '/jsonp_send'), 'i=' + id);

	  var iframe = createIframe(id);
	  iframe.id = id;
	  iframe.style.display = 'none';
	  form.appendChild(iframe);

	  try {
	    area.value = payload;
	  } catch (e) {
	    // seriously broken browsers get here
	  }
	  form.submit();

	  var completed = function(err) {
	    debug('completed', id, err);
	    if (!iframe.onerror) {
	      return;
	    }
	    iframe.onreadystatechange = iframe.onerror = iframe.onload = null;
	    // Opera mini doesn't like if we GC iframe
	    // immediately, thus this timeout.
	    setTimeout(function() {
	      debug('cleaning up', id);
	      iframe.parentNode.removeChild(iframe);
	      iframe = null;
	    }, 500);
	    area.value = '';
	    // It is not possible to detect if the iframe succeeded or
	    // failed to submit our form.
	    callback(err);
	  };
	  iframe.onerror = function() {
	    debug('onerror', id);
	    completed();
	  };
	  iframe.onload = function() {
	    debug('onload', id);
	    completed();
	  };
	  iframe.onreadystatechange = function(e) {
	    debug('onreadystatechange', id, iframe.readyState, e);
	    if (iframe.readyState === 'complete') {
	      completed();
	    }
	  };
	  return function() {
	    debug('aborted', id);
	    completed(new Error('Aborted'));
	  };
	};

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 55 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	__webpack_require__(56);

	var URL = __webpack_require__(16)
	  , inherits = __webpack_require__(22)
	  , JSON3 = __webpack_require__(43)
	  , random = __webpack_require__(13)
	  , escape = __webpack_require__(57)
	  , urlUtils = __webpack_require__(15)
	  , eventUtils = __webpack_require__(12)
	  , transport = __webpack_require__(58)
	  , objectUtils = __webpack_require__(47)
	  , browser = __webpack_require__(35)
	  , log = __webpack_require__(59)
	  , Event = __webpack_require__(60)
	  , EventTarget = __webpack_require__(24)
	  , loc = __webpack_require__(61)
	  , CloseEvent = __webpack_require__(62)
	  , TransportMessageEvent = __webpack_require__(63)
	  , InfoReceiver = __webpack_require__(64)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:main');
	}

	var transports;

	// follow constructor steps defined at http://dev.w3.org/html5/websockets/#the-websocket-interface
	function SockJS(url, protocols, options) {
	  if (!(this instanceof SockJS)) {
	    return new SockJS(url, protocols, options);
	  }
	  if (arguments.length < 1) {
	    throw new TypeError("Failed to construct 'SockJS: 1 argument required, but only 0 present");
	  }
	  EventTarget.call(this);

	  this.readyState = SockJS.CONNECTING;
	  this.extensions = '';
	  this.protocol = '';

	  // non-standard extension
	  options = options || {};
	  if (options.protocols_whitelist) {
	    log.warn("'protocols_whitelist' is DEPRECATED. Use 'transports' instead.");
	  }
	  this._transportsWhitelist = options.transports;
	  this._transportOptions = options.transportOptions || {};

	  var sessionId = options.sessionId || 8;
	  if (typeof sessionId === 'function') {
	    this._generateSessionId = sessionId;
	  } else if (typeof sessionId === 'number') {
	    this._generateSessionId = function() {
	      return random.string(sessionId);
	    };
	  } else {
	    throw new TypeError('If sessionId is used in the options, it needs to be a number or a function.');
	  }

	  this._server = options.server || random.numberString(1000);

	  // Step 1 of WS spec - parse and validate the url. Issue #8
	  var parsedUrl = new URL(url);
	  if (!parsedUrl.host || !parsedUrl.protocol) {
	    throw new SyntaxError("The URL '" + url + "' is invalid");
	  } else if (parsedUrl.hash) {
	    throw new SyntaxError('The URL must not contain a fragment');
	  } else if (parsedUrl.protocol !== 'http:' && parsedUrl.protocol !== 'https:') {
	    throw new SyntaxError("The URL's scheme must be either 'http:' or 'https:'. '" + parsedUrl.protocol + "' is not allowed.");
	  }

	  var secure = parsedUrl.protocol === 'https:';
	  // Step 2 - don't allow secure origin with an insecure protocol
	  if (loc.protocol === 'https:' && !secure) {
	    throw new Error('SecurityError: An insecure SockJS connection may not be initiated from a page loaded over HTTPS');
	  }

	  // Step 3 - check port access - no need here
	  // Step 4 - parse protocols argument
	  if (!protocols) {
	    protocols = [];
	  } else if (!Array.isArray(protocols)) {
	    protocols = [protocols];
	  }

	  // Step 5 - check protocols argument
	  var sortedProtocols = protocols.sort();
	  sortedProtocols.forEach(function(proto, i) {
	    if (!proto) {
	      throw new SyntaxError("The protocols entry '" + proto + "' is invalid.");
	    }
	    if (i < (sortedProtocols.length - 1) && proto === sortedProtocols[i + 1]) {
	      throw new SyntaxError("The protocols entry '" + proto + "' is duplicated.");
	    }
	  });

	  // Step 6 - convert origin
	  var o = urlUtils.getOrigin(loc.href);
	  this._origin = o ? o.toLowerCase() : null;

	  // remove the trailing slash
	  parsedUrl.set('pathname', parsedUrl.pathname.replace(/\/+$/, ''));

	  // store the sanitized url
	  this.url = parsedUrl.href;
	  debug('using url', this.url);

	  // Step 7 - start connection in background
	  // obtain server info
	  // http://sockjs.github.io/sockjs-protocol/sockjs-protocol-0.3.3.html#section-26
	  this._urlInfo = {
	    nullOrigin: !browser.hasDomain()
	  , sameOrigin: urlUtils.isOriginEqual(this.url, loc.href)
	  , sameScheme: urlUtils.isSchemeEqual(this.url, loc.href)
	  };

	  this._ir = new InfoReceiver(this.url, this._urlInfo);
	  this._ir.once('finish', this._receiveInfo.bind(this));
	}

	inherits(SockJS, EventTarget);

	function userSetCode(code) {
	  return code === 1000 || (code >= 3000 && code <= 4999);
	}

	SockJS.prototype.close = function(code, reason) {
	  // Step 1
	  if (code && !userSetCode(code)) {
	    throw new Error('InvalidAccessError: Invalid code');
	  }
	  // Step 2.4 states the max is 123 bytes, but we are just checking length
	  if (reason && reason.length > 123) {
	    throw new SyntaxError('reason argument has an invalid length');
	  }

	  // Step 3.1
	  if (this.readyState === SockJS.CLOSING || this.readyState === SockJS.CLOSED) {
	    return;
	  }

	  // TODO look at docs to determine how to set this
	  var wasClean = true;
	  this._close(code || 1000, reason || 'Normal closure', wasClean);
	};

	SockJS.prototype.send = function(data) {
	  // #13 - convert anything non-string to string
	  // TODO this currently turns objects into [object Object]
	  if (typeof data !== 'string') {
	    data = '' + data;
	  }
	  if (this.readyState === SockJS.CONNECTING) {
	    throw new Error('InvalidStateError: The connection has not been established yet');
	  }
	  if (this.readyState !== SockJS.OPEN) {
	    return;
	  }
	  this._transport.send(escape.quote(data));
	};

	SockJS.version = __webpack_require__(45);

	SockJS.CONNECTING = 0;
	SockJS.OPEN = 1;
	SockJS.CLOSING = 2;
	SockJS.CLOSED = 3;

	SockJS.prototype._receiveInfo = function(info, rtt) {
	  debug('_receiveInfo', rtt);
	  this._ir = null;
	  if (!info) {
	    this._close(1002, 'Cannot connect to server');
	    return;
	  }

	  // establish a round-trip timeout (RTO) based on the
	  // round-trip time (RTT)
	  this._rto = this.countRTO(rtt);
	  // allow server to override url used for the actual transport
	  this._transUrl = info.base_url ? info.base_url : this.url;
	  info = objectUtils.extend(info, this._urlInfo);
	  debug('info', info);
	  // determine list of desired and supported transports
	  var enabledTransports = transports.filterToEnabled(this._transportsWhitelist, info);
	  this._transports = enabledTransports.main;
	  debug(this._transports.length + ' enabled transports');

	  this._connect();
	};

	SockJS.prototype._connect = function() {
	  for (var Transport = this._transports.shift(); Transport; Transport = this._transports.shift()) {
	    debug('attempt', Transport.transportName);
	    if (Transport.needBody) {
	      if (!global.document.body ||
	          (typeof global.document.readyState !== 'undefined' &&
	            global.document.readyState !== 'complete' &&
	            global.document.readyState !== 'interactive')) {
	        debug('waiting for body');
	        this._transports.unshift(Transport);
	        eventUtils.attachEvent('load', this._connect.bind(this));
	        return;
	      }
	    }

	    // calculate timeout based on RTO and round trips. Default to 5s
	    var timeoutMs = (this._rto * Transport.roundTrips) || 5000;
	    this._transportTimeoutId = setTimeout(this._transportTimeout.bind(this), timeoutMs);
	    debug('using timeout', timeoutMs);

	    var transportUrl = urlUtils.addPath(this._transUrl, '/' + this._server + '/' + this._generateSessionId());
	    var options = this._transportOptions[Transport.transportName];
	    debug('transport url', transportUrl);
	    var transportObj = new Transport(transportUrl, this._transUrl, options);
	    transportObj.on('message', this._transportMessage.bind(this));
	    transportObj.once('close', this._transportClose.bind(this));
	    transportObj.transportName = Transport.transportName;
	    this._transport = transportObj;

	    return;
	  }
	  this._close(2000, 'All transports failed', false);
	};

	SockJS.prototype._transportTimeout = function() {
	  debug('_transportTimeout');
	  if (this.readyState === SockJS.CONNECTING) {
	    if (this._transport) {
	      this._transport.close();
	    }

	    this._transportClose(2007, 'Transport timed out');
	  }
	};

	SockJS.prototype._transportMessage = function(msg) {
	  debug('_transportMessage', msg);
	  var self = this
	    , type = msg.slice(0, 1)
	    , content = msg.slice(1)
	    , payload
	    ;

	  // first check for messages that don't need a payload
	  switch (type) {
	    case 'o':
	      this._open();
	      return;
	    case 'h':
	      this.dispatchEvent(new Event('heartbeat'));
	      debug('heartbeat', this.transport);
	      return;
	  }

	  if (content) {
	    try {
	      payload = JSON3.parse(content);
	    } catch (e) {
	      debug('bad json', content);
	    }
	  }

	  if (typeof payload === 'undefined') {
	    debug('empty payload', content);
	    return;
	  }

	  switch (type) {
	    case 'a':
	      if (Array.isArray(payload)) {
	        payload.forEach(function(p) {
	          debug('message', self.transport, p);
	          self.dispatchEvent(new TransportMessageEvent(p));
	        });
	      }
	      break;
	    case 'm':
	      debug('message', this.transport, payload);
	      this.dispatchEvent(new TransportMessageEvent(payload));
	      break;
	    case 'c':
	      if (Array.isArray(payload) && payload.length === 2) {
	        this._close(payload[0], payload[1], true);
	      }
	      break;
	  }
	};

	SockJS.prototype._transportClose = function(code, reason) {
	  debug('_transportClose', this.transport, code, reason);
	  if (this._transport) {
	    this._transport.removeAllListeners();
	    this._transport = null;
	    this.transport = null;
	  }

	  if (!userSetCode(code) && code !== 2000 && this.readyState === SockJS.CONNECTING) {
	    this._connect();
	    return;
	  }

	  this._close(code, reason);
	};

	SockJS.prototype._open = function() {
	  debug('_open', this._transport.transportName, this.readyState);
	  if (this.readyState === SockJS.CONNECTING) {
	    if (this._transportTimeoutId) {
	      clearTimeout(this._transportTimeoutId);
	      this._transportTimeoutId = null;
	    }
	    this.readyState = SockJS.OPEN;
	    this.transport = this._transport.transportName;
	    this.dispatchEvent(new Event('open'));
	    debug('connected', this.transport);
	  } else {
	    // The server might have been restarted, and lost track of our
	    // connection.
	    this._close(1006, 'Server lost session');
	  }
	};

	SockJS.prototype._close = function(code, reason, wasClean) {
	  debug('_close', this.transport, code, reason, wasClean, this.readyState);
	  var forceFail = false;

	  if (this._ir) {
	    forceFail = true;
	    this._ir.close();
	    this._ir = null;
	  }
	  if (this._transport) {
	    this._transport.close();
	    this._transport = null;
	    this.transport = null;
	  }

	  if (this.readyState === SockJS.CLOSED) {
	    throw new Error('InvalidStateError: SockJS has already been closed');
	  }

	  this.readyState = SockJS.CLOSING;
	  setTimeout(function() {
	    this.readyState = SockJS.CLOSED;

	    if (forceFail) {
	      this.dispatchEvent(new Event('error'));
	    }

	    var e = new CloseEvent('close');
	    e.wasClean = wasClean || false;
	    e.code = code || 1000;
	    e.reason = reason;

	    this.dispatchEvent(e);
	    this.onmessage = this.onclose = this.onerror = null;
	    debug('disconnected');
	  }.bind(this), 0);
	};

	// See: http://www.erg.abdn.ac.uk/~gerrit/dccp/notes/ccid2/rto_estimator/
	// and RFC 2988.
	SockJS.prototype.countRTO = function(rtt) {
	  // In a local environment, when using IE8/9 and the `jsonp-polling`
	  // transport the time needed to establish a connection (the time that pass
	  // from the opening of the transport to the call of `_dispatchOpen`) is
	  // around 200msec (the lower bound used in the article above) and this
	  // causes spurious timeouts. For this reason we calculate a value slightly
	  // larger than that used in the article.
	  if (rtt > 100) {
	    return 4 * rtt; // rto > 400msec
	  }
	  return 300 + rtt; // 300msec < rto <= 400msec
	};

	module.exports = function(availableTransports) {
	  transports = transport(availableTransports);
	  __webpack_require__(69)(SockJS, availableTransports);
	  return SockJS;
	};

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 56 */
/***/ function(module, exports) {

	/* eslint-disable */
	/* jscs: disable */
	'use strict';

	// pulled specific shims from https://github.com/es-shims/es5-shim

	var ArrayPrototype = Array.prototype;
	var ObjectPrototype = Object.prototype;
	var FunctionPrototype = Function.prototype;
	var StringPrototype = String.prototype;
	var array_slice = ArrayPrototype.slice;

	var _toString = ObjectPrototype.toString;
	var isFunction = function (val) {
	    return ObjectPrototype.toString.call(val) === '[object Function]';
	};
	var isArray = function isArray(obj) {
	    return _toString.call(obj) === '[object Array]';
	};
	var isString = function isString(obj) {
	    return _toString.call(obj) === '[object String]';
	};

	var supportsDescriptors = Object.defineProperty && (function () {
	    try {
	        Object.defineProperty({}, 'x', {});
	        return true;
	    } catch (e) { /* this is ES3 */
	        return false;
	    }
	}());

	// Define configurable, writable and non-enumerable props
	// if they don't exist.
	var defineProperty;
	if (supportsDescriptors) {
	    defineProperty = function (object, name, method, forceAssign) {
	        if (!forceAssign && (name in object)) { return; }
	        Object.defineProperty(object, name, {
	            configurable: true,
	            enumerable: false,
	            writable: true,
	            value: method
	        });
	    };
	} else {
	    defineProperty = function (object, name, method, forceAssign) {
	        if (!forceAssign && (name in object)) { return; }
	        object[name] = method;
	    };
	}
	var defineProperties = function (object, map, forceAssign) {
	    for (var name in map) {
	        if (ObjectPrototype.hasOwnProperty.call(map, name)) {
	          defineProperty(object, name, map[name], forceAssign);
	        }
	    }
	};

	var toObject = function (o) {
	    if (o == null) { // this matches both null and undefined
	        throw new TypeError("can't convert " + o + ' to object');
	    }
	    return Object(o);
	};

	//
	// Util
	// ======
	//

	// ES5 9.4
	// http://es5.github.com/#x9.4
	// http://jsperf.com/to-integer

	function toInteger(num) {
	    var n = +num;
	    if (n !== n) { // isNaN
	        n = 0;
	    } else if (n !== 0 && n !== (1 / 0) && n !== -(1 / 0)) {
	        n = (n > 0 || -1) * Math.floor(Math.abs(n));
	    }
	    return n;
	}

	function ToUint32(x) {
	    return x >>> 0;
	}

	//
	// Function
	// ========
	//

	// ES-5 15.3.4.5
	// http://es5.github.com/#x15.3.4.5

	function Empty() {}

	defineProperties(FunctionPrototype, {
	    bind: function bind(that) { // .length is 1
	        // 1. Let Target be the this value.
	        var target = this;
	        // 2. If IsCallable(Target) is false, throw a TypeError exception.
	        if (!isFunction(target)) {
	            throw new TypeError('Function.prototype.bind called on incompatible ' + target);
	        }
	        // 3. Let A be a new (possibly empty) internal list of all of the
	        //   argument values provided after thisArg (arg1, arg2 etc), in order.
	        // XXX slicedArgs will stand in for "A" if used
	        var args = array_slice.call(arguments, 1); // for normal call
	        // 4. Let F be a new native ECMAScript object.
	        // 11. Set the [[Prototype]] internal property of F to the standard
	        //   built-in Function prototype object as specified in 15.3.3.1.
	        // 12. Set the [[Call]] internal property of F as described in
	        //   15.3.4.5.1.
	        // 13. Set the [[Construct]] internal property of F as described in
	        //   15.3.4.5.2.
	        // 14. Set the [[HasInstance]] internal property of F as described in
	        //   15.3.4.5.3.
	        var binder = function () {

	            if (this instanceof bound) {
	                // 15.3.4.5.2 [[Construct]]
	                // When the [[Construct]] internal method of a function object,
	                // F that was created using the bind function is called with a
	                // list of arguments ExtraArgs, the following steps are taken:
	                // 1. Let target be the value of F's [[TargetFunction]]
	                //   internal property.
	                // 2. If target has no [[Construct]] internal method, a
	                //   TypeError exception is thrown.
	                // 3. Let boundArgs be the value of F's [[BoundArgs]] internal
	                //   property.
	                // 4. Let args be a new list containing the same values as the
	                //   list boundArgs in the same order followed by the same
	                //   values as the list ExtraArgs in the same order.
	                // 5. Return the result of calling the [[Construct]] internal
	                //   method of target providing args as the arguments.

	                var result = target.apply(
	                    this,
	                    args.concat(array_slice.call(arguments))
	                );
	                if (Object(result) === result) {
	                    return result;
	                }
	                return this;

	            } else {
	                // 15.3.4.5.1 [[Call]]
	                // When the [[Call]] internal method of a function object, F,
	                // which was created using the bind function is called with a
	                // this value and a list of arguments ExtraArgs, the following
	                // steps are taken:
	                // 1. Let boundArgs be the value of F's [[BoundArgs]] internal
	                //   property.
	                // 2. Let boundThis be the value of F's [[BoundThis]] internal
	                //   property.
	                // 3. Let target be the value of F's [[TargetFunction]] internal
	                //   property.
	                // 4. Let args be a new list containing the same values as the
	                //   list boundArgs in the same order followed by the same
	                //   values as the list ExtraArgs in the same order.
	                // 5. Return the result of calling the [[Call]] internal method
	                //   of target providing boundThis as the this value and
	                //   providing args as the arguments.

	                // equiv: target.call(this, ...boundArgs, ...args)
	                return target.apply(
	                    that,
	                    args.concat(array_slice.call(arguments))
	                );

	            }

	        };

	        // 15. If the [[Class]] internal property of Target is "Function", then
	        //     a. Let L be the length property of Target minus the length of A.
	        //     b. Set the length own property of F to either 0 or L, whichever is
	        //       larger.
	        // 16. Else set the length own property of F to 0.

	        var boundLength = Math.max(0, target.length - args.length);

	        // 17. Set the attributes of the length own property of F to the values
	        //   specified in 15.3.5.1.
	        var boundArgs = [];
	        for (var i = 0; i < boundLength; i++) {
	            boundArgs.push('$' + i);
	        }

	        // XXX Build a dynamic function with desired amount of arguments is the only
	        // way to set the length property of a function.
	        // In environments where Content Security Policies enabled (Chrome extensions,
	        // for ex.) all use of eval or Function costructor throws an exception.
	        // However in all of these environments Function.prototype.bind exists
	        // and so this code will never be executed.
	        var bound = Function('binder', 'return function (' + boundArgs.join(',') + '){ return binder.apply(this, arguments); }')(binder);

	        if (target.prototype) {
	            Empty.prototype = target.prototype;
	            bound.prototype = new Empty();
	            // Clean up dangling references.
	            Empty.prototype = null;
	        }

	        // TODO
	        // 18. Set the [[Extensible]] internal property of F to true.

	        // TODO
	        // 19. Let thrower be the [[ThrowTypeError]] function Object (13.2.3).
	        // 20. Call the [[DefineOwnProperty]] internal method of F with
	        //   arguments "caller", PropertyDescriptor {[[Get]]: thrower, [[Set]]:
	        //   thrower, [[Enumerable]]: false, [[Configurable]]: false}, and
	        //   false.
	        // 21. Call the [[DefineOwnProperty]] internal method of F with
	        //   arguments "arguments", PropertyDescriptor {[[Get]]: thrower,
	        //   [[Set]]: thrower, [[Enumerable]]: false, [[Configurable]]: false},
	        //   and false.

	        // TODO
	        // NOTE Function objects created using Function.prototype.bind do not
	        // have a prototype property or the [[Code]], [[FormalParameters]], and
	        // [[Scope]] internal properties.
	        // XXX can't delete prototype in pure-js.

	        // 22. Return F.
	        return bound;
	    }
	});

	//
	// Array
	// =====
	//

	// ES5 15.4.3.2
	// http://es5.github.com/#x15.4.3.2
	// https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/Array/isArray
	defineProperties(Array, { isArray: isArray });


	var boxedString = Object('a');
	var splitString = boxedString[0] !== 'a' || !(0 in boxedString);

	var properlyBoxesContext = function properlyBoxed(method) {
	    // Check node 0.6.21 bug where third parameter is not boxed
	    var properlyBoxesNonStrict = true;
	    var properlyBoxesStrict = true;
	    if (method) {
	        method.call('foo', function (_, __, context) {
	            if (typeof context !== 'object') { properlyBoxesNonStrict = false; }
	        });

	        method.call([1], function () {
	            'use strict';
	            properlyBoxesStrict = typeof this === 'string';
	        }, 'x');
	    }
	    return !!method && properlyBoxesNonStrict && properlyBoxesStrict;
	};

	defineProperties(ArrayPrototype, {
	    forEach: function forEach(fun /*, thisp*/) {
	        var object = toObject(this),
	            self = splitString && isString(this) ? this.split('') : object,
	            thisp = arguments[1],
	            i = -1,
	            length = self.length >>> 0;

	        // If no callback function or if callback is not a callable function
	        if (!isFunction(fun)) {
	            throw new TypeError(); // TODO message
	        }

	        while (++i < length) {
	            if (i in self) {
	                // Invoke the callback function with call, passing arguments:
	                // context, property value, property key, thisArg object
	                // context
	                fun.call(thisp, self[i], i, object);
	            }
	        }
	    }
	}, !properlyBoxesContext(ArrayPrototype.forEach));

	// ES5 15.4.4.14
	// http://es5.github.com/#x15.4.4.14
	// https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/Array/indexOf
	var hasFirefox2IndexOfBug = Array.prototype.indexOf && [0, 1].indexOf(1, 2) !== -1;
	defineProperties(ArrayPrototype, {
	    indexOf: function indexOf(sought /*, fromIndex */ ) {
	        var self = splitString && isString(this) ? this.split('') : toObject(this),
	            length = self.length >>> 0;

	        if (!length) {
	            return -1;
	        }

	        var i = 0;
	        if (arguments.length > 1) {
	            i = toInteger(arguments[1]);
	        }

	        // handle negative indices
	        i = i >= 0 ? i : Math.max(0, length + i);
	        for (; i < length; i++) {
	            if (i in self && self[i] === sought) {
	                return i;
	            }
	        }
	        return -1;
	    }
	}, hasFirefox2IndexOfBug);

	//
	// String
	// ======
	//

	// ES5 15.5.4.14
	// http://es5.github.com/#x15.5.4.14

	// [bugfix, IE lt 9, firefox 4, Konqueror, Opera, obscure browsers]
	// Many browsers do not split properly with regular expressions or they
	// do not perform the split correctly under obscure conditions.
	// See http://blog.stevenlevithan.com/archives/cross-browser-split
	// I've tested in many browsers and this seems to cover the deviant ones:
	//    'ab'.split(/(?:ab)*/) should be ["", ""], not [""]
	//    '.'.split(/(.?)(.?)/) should be ["", ".", "", ""], not ["", ""]
	//    'tesst'.split(/(s)*/) should be ["t", undefined, "e", "s", "t"], not
	//       [undefined, "t", undefined, "e", ...]
	//    ''.split(/.?/) should be [], not [""]
	//    '.'.split(/()()/) should be ["."], not ["", "", "."]

	var string_split = StringPrototype.split;
	if (
	    'ab'.split(/(?:ab)*/).length !== 2 ||
	    '.'.split(/(.?)(.?)/).length !== 4 ||
	    'tesst'.split(/(s)*/)[1] === 't' ||
	    'test'.split(/(?:)/, -1).length !== 4 ||
	    ''.split(/.?/).length ||
	    '.'.split(/()()/).length > 1
	) {
	    (function () {
	        var compliantExecNpcg = /()??/.exec('')[1] === void 0; // NPCG: nonparticipating capturing group

	        StringPrototype.split = function (separator, limit) {
	            var string = this;
	            if (separator === void 0 && limit === 0) {
	                return [];
	            }

	            // If `separator` is not a regex, use native split
	            if (_toString.call(separator) !== '[object RegExp]') {
	                return string_split.call(this, separator, limit);
	            }

	            var output = [],
	                flags = (separator.ignoreCase ? 'i' : '') +
	                        (separator.multiline  ? 'm' : '') +
	                        (separator.extended   ? 'x' : '') + // Proposed for ES6
	                        (separator.sticky     ? 'y' : ''), // Firefox 3+
	                lastLastIndex = 0,
	                // Make `global` and avoid `lastIndex` issues by working with a copy
	                separator2, match, lastIndex, lastLength;
	            separator = new RegExp(separator.source, flags + 'g');
	            string += ''; // Type-convert
	            if (!compliantExecNpcg) {
	                // Doesn't need flags gy, but they don't hurt
	                separator2 = new RegExp('^' + separator.source + '$(?!\\s)', flags);
	            }
	            /* Values for `limit`, per the spec:
	             * If undefined: 4294967295 // Math.pow(2, 32) - 1
	             * If 0, Infinity, or NaN: 0
	             * If positive number: limit = Math.floor(limit); if (limit > 4294967295) limit -= 4294967296;
	             * If negative number: 4294967296 - Math.floor(Math.abs(limit))
	             * If other: Type-convert, then use the above rules
	             */
	            limit = limit === void 0 ?
	                -1 >>> 0 : // Math.pow(2, 32) - 1
	                ToUint32(limit);
	            while (match = separator.exec(string)) {
	                // `separator.lastIndex` is not reliable cross-browser
	                lastIndex = match.index + match[0].length;
	                if (lastIndex > lastLastIndex) {
	                    output.push(string.slice(lastLastIndex, match.index));
	                    // Fix browsers whose `exec` methods don't consistently return `undefined` for
	                    // nonparticipating capturing groups
	                    if (!compliantExecNpcg && match.length > 1) {
	                        match[0].replace(separator2, function () {
	                            for (var i = 1; i < arguments.length - 2; i++) {
	                                if (arguments[i] === void 0) {
	                                    match[i] = void 0;
	                                }
	                            }
	                        });
	                    }
	                    if (match.length > 1 && match.index < string.length) {
	                        ArrayPrototype.push.apply(output, match.slice(1));
	                    }
	                    lastLength = match[0].length;
	                    lastLastIndex = lastIndex;
	                    if (output.length >= limit) {
	                        break;
	                    }
	                }
	                if (separator.lastIndex === match.index) {
	                    separator.lastIndex++; // Avoid an infinite loop
	                }
	            }
	            if (lastLastIndex === string.length) {
	                if (lastLength || !separator.test('')) {
	                    output.push('');
	                }
	            } else {
	                output.push(string.slice(lastLastIndex));
	            }
	            return output.length > limit ? output.slice(0, limit) : output;
	        };
	    }());

	// [bugfix, chrome]
	// If separator is undefined, then the result array contains just one String,
	// which is the this value (converted to a String). If limit is not undefined,
	// then the output array is truncated so that it contains no more than limit
	// elements.
	// "0".split(undefined, 0) -> []
	} else if ('0'.split(void 0, 0).length) {
	    StringPrototype.split = function split(separator, limit) {
	        if (separator === void 0 && limit === 0) { return []; }
	        return string_split.call(this, separator, limit);
	    };
	}

	// ECMA-262, 3rd B.2.3
	// Not an ECMAScript standard, although ECMAScript 3rd Edition has a
	// non-normative section suggesting uniform semantics and it should be
	// normalized across all browsers
	// [bugfix, IE lt 9] IE < 9 substr() with negative value not working in IE
	var string_substr = StringPrototype.substr;
	var hasNegativeSubstrBug = ''.substr && '0b'.substr(-1) !== 'b';
	defineProperties(StringPrototype, {
	    substr: function substr(start, length) {
	        return string_substr.call(
	            this,
	            start < 0 ? ((start = this.length + start) < 0 ? 0 : start) : start,
	            length
	        );
	    }
	}, hasNegativeSubstrBug);


/***/ },
/* 57 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var JSON3 = __webpack_require__(43);

	// Some extra characters that Chrome gets wrong, and substitutes with
	// something else on the wire.
	// eslint-disable-next-line no-control-regex
	var extraEscapable = /[\x00-\x1f\ud800-\udfff\ufffe\uffff\u0300-\u0333\u033d-\u0346\u034a-\u034c\u0350-\u0352\u0357-\u0358\u035c-\u0362\u0374\u037e\u0387\u0591-\u05af\u05c4\u0610-\u0617\u0653-\u0654\u0657-\u065b\u065d-\u065e\u06df-\u06e2\u06eb-\u06ec\u0730\u0732-\u0733\u0735-\u0736\u073a\u073d\u073f-\u0741\u0743\u0745\u0747\u07eb-\u07f1\u0951\u0958-\u095f\u09dc-\u09dd\u09df\u0a33\u0a36\u0a59-\u0a5b\u0a5e\u0b5c-\u0b5d\u0e38-\u0e39\u0f43\u0f4d\u0f52\u0f57\u0f5c\u0f69\u0f72-\u0f76\u0f78\u0f80-\u0f83\u0f93\u0f9d\u0fa2\u0fa7\u0fac\u0fb9\u1939-\u193a\u1a17\u1b6b\u1cda-\u1cdb\u1dc0-\u1dcf\u1dfc\u1dfe\u1f71\u1f73\u1f75\u1f77\u1f79\u1f7b\u1f7d\u1fbb\u1fbe\u1fc9\u1fcb\u1fd3\u1fdb\u1fe3\u1feb\u1fee-\u1fef\u1ff9\u1ffb\u1ffd\u2000-\u2001\u20d0-\u20d1\u20d4-\u20d7\u20e7-\u20e9\u2126\u212a-\u212b\u2329-\u232a\u2adc\u302b-\u302c\uaab2-\uaab3\uf900-\ufa0d\ufa10\ufa12\ufa15-\ufa1e\ufa20\ufa22\ufa25-\ufa26\ufa2a-\ufa2d\ufa30-\ufa6d\ufa70-\ufad9\ufb1d\ufb1f\ufb2a-\ufb36\ufb38-\ufb3c\ufb3e\ufb40-\ufb41\ufb43-\ufb44\ufb46-\ufb4e\ufff0-\uffff]/g
	  , extraLookup;

	// This may be quite slow, so let's delay until user actually uses bad
	// characters.
	var unrollLookup = function(escapable) {
	  var i;
	  var unrolled = {};
	  var c = [];
	  for (i = 0; i < 65536; i++) {
	    c.push( String.fromCharCode(i) );
	  }
	  escapable.lastIndex = 0;
	  c.join('').replace(escapable, function(a) {
	    unrolled[ a ] = '\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice(-4);
	    return '';
	  });
	  escapable.lastIndex = 0;
	  return unrolled;
	};

	// Quote string, also taking care of unicode characters that browsers
	// often break. Especially, take care of unicode surrogates:
	// http://en.wikipedia.org/wiki/Mapping_of_Unicode_characters#Surrogates
	module.exports = {
	  quote: function(string) {
	    var quoted = JSON3.stringify(string);

	    // In most cases this should be very fast and good enough.
	    extraEscapable.lastIndex = 0;
	    if (!extraEscapable.test(quoted)) {
	      return quoted;
	    }

	    if (!extraLookup) {
	      extraLookup = unrollLookup(extraEscapable);
	    }

	    return quoted.replace(extraEscapable, function(a) {
	      return extraLookup[a];
	    });
	  }
	};


/***/ },
/* 58 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:utils:transport');
	}

	module.exports = function(availableTransports) {
	  return {
	    filterToEnabled: function(transportsWhitelist, info) {
	      var transports = {
	        main: []
	      , facade: []
	      };
	      if (!transportsWhitelist) {
	        transportsWhitelist = [];
	      } else if (typeof transportsWhitelist === 'string') {
	        transportsWhitelist = [transportsWhitelist];
	      }

	      availableTransports.forEach(function(trans) {
	        if (!trans) {
	          return;
	        }

	        if (trans.transportName === 'websocket' && info.websocket === false) {
	          debug('disabled from server', 'websocket');
	          return;
	        }

	        if (transportsWhitelist.length &&
	            transportsWhitelist.indexOf(trans.transportName) === -1) {
	          debug('not in whitelist', trans.transportName);
	          return;
	        }

	        if (trans.enabled(info)) {
	          debug('enabled', trans.transportName);
	          transports.main.push(trans);
	          if (trans.facadeTransport) {
	            transports.facade.push(trans.facadeTransport);
	          }
	        } else {
	          debug('disabled', trans.transportName);
	        }
	      });
	      return transports;
	    }
	  };
	};

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 59 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	var logObject = {};
	['log', 'debug', 'warn'].forEach(function (level) {
	  var levelExists;

	  try {
	    levelExists = global.console && global.console[level] && global.console[level].apply;
	  } catch(e) {
	    // do nothing
	  }

	  logObject[level] = levelExists ? function () {
	    return global.console[level].apply(global.console, arguments);
	  } : (level === 'log' ? function () {} : logObject.log);
	});

	module.exports = logObject;

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 60 */
/***/ function(module, exports) {

	'use strict';

	function Event(eventType) {
	  this.type = eventType;
	}

	Event.prototype.initEvent = function(eventType, canBubble, cancelable) {
	  this.type = eventType;
	  this.bubbles = canBubble;
	  this.cancelable = cancelable;
	  this.timeStamp = +new Date();
	  return this;
	};

	Event.prototype.stopPropagation = function() {};
	Event.prototype.preventDefault = function() {};

	Event.CAPTURING_PHASE = 1;
	Event.AT_TARGET = 2;
	Event.BUBBLING_PHASE = 3;

	module.exports = Event;


/***/ },
/* 61 */
/***/ function(module, exports) {

	/* WEBPACK VAR INJECTION */(function(global) {'use strict';

	module.exports = global.location || {
	  origin: 'http://localhost:80'
	, protocol: 'http:'
	, host: 'localhost'
	, port: 80
	, href: 'http://localhost/'
	, hash: ''
	};

	/* WEBPACK VAR INJECTION */}.call(exports, (function() { return this; }())))

/***/ },
/* 62 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , Event = __webpack_require__(60)
	  ;

	function CloseEvent() {
	  Event.call(this);
	  this.initEvent('close', false, false);
	  this.wasClean = false;
	  this.code = 0;
	  this.reason = '';
	}

	inherits(CloseEvent, Event);

	module.exports = CloseEvent;


/***/ },
/* 63 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , Event = __webpack_require__(60)
	  ;

	function TransportMessageEvent(data) {
	  Event.call(this);
	  this.initEvent('message', false, false);
	  this.data = data;
	}

	inherits(TransportMessageEvent, Event);

	module.exports = TransportMessageEvent;


/***/ },
/* 64 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var EventEmitter = __webpack_require__(23).EventEmitter
	  , inherits = __webpack_require__(22)
	  , urlUtils = __webpack_require__(15)
	  , XDR = __webpack_require__(37)
	  , XHRCors = __webpack_require__(32)
	  , XHRLocal = __webpack_require__(34)
	  , XHRFake = __webpack_require__(65)
	  , InfoIframe = __webpack_require__(66)
	  , InfoAjax = __webpack_require__(68)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:info-receiver');
	}

	function InfoReceiver(baseUrl, urlInfo) {
	  debug(baseUrl);
	  var self = this;
	  EventEmitter.call(this);

	  setTimeout(function() {
	    self.doXhr(baseUrl, urlInfo);
	  }, 0);
	}

	inherits(InfoReceiver, EventEmitter);

	// TODO this is currently ignoring the list of available transports and the whitelist

	InfoReceiver._getReceiver = function(baseUrl, url, urlInfo) {
	  // determine method of CORS support (if needed)
	  if (urlInfo.sameOrigin) {
	    return new InfoAjax(url, XHRLocal);
	  }
	  if (XHRCors.enabled) {
	    return new InfoAjax(url, XHRCors);
	  }
	  if (XDR.enabled && urlInfo.sameScheme) {
	    return new InfoAjax(url, XDR);
	  }
	  if (InfoIframe.enabled()) {
	    return new InfoIframe(baseUrl, url);
	  }
	  return new InfoAjax(url, XHRFake);
	};

	InfoReceiver.prototype.doXhr = function(baseUrl, urlInfo) {
	  var self = this
	    , url = urlUtils.addPath(baseUrl, '/info')
	    ;
	  debug('doXhr', url);

	  this.xo = InfoReceiver._getReceiver(baseUrl, url, urlInfo);

	  this.timeoutRef = setTimeout(function() {
	    debug('timeout');
	    self._cleanup(false);
	    self.emit('finish');
	  }, InfoReceiver.timeout);

	  this.xo.once('finish', function(info, rtt) {
	    debug('finish', info, rtt);
	    self._cleanup(true);
	    self.emit('finish', info, rtt);
	  });
	};

	InfoReceiver.prototype._cleanup = function(wasClean) {
	  debug('_cleanup');
	  clearTimeout(this.timeoutRef);
	  this.timeoutRef = null;
	  if (!wasClean && this.xo) {
	    this.xo.close();
	  }
	  this.xo = null;
	};

	InfoReceiver.prototype.close = function() {
	  debug('close');
	  this.removeAllListeners();
	  this._cleanup(false);
	};

	InfoReceiver.timeout = 8000;

	module.exports = InfoReceiver;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 65 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var EventEmitter = __webpack_require__(23).EventEmitter
	  , inherits = __webpack_require__(22)
	  ;

	function XHRFake(/* method, url, payload, opts */) {
	  var self = this;
	  EventEmitter.call(this);

	  this.to = setTimeout(function() {
	    self.emit('finish', 200, '{}');
	  }, XHRFake.timeout);
	}

	inherits(XHRFake, EventEmitter);

	XHRFake.prototype.close = function() {
	  clearTimeout(this.to);
	};

	XHRFake.timeout = 2000;

	module.exports = XHRFake;


/***/ },
/* 66 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process, global) {'use strict';

	var EventEmitter = __webpack_require__(23).EventEmitter
	  , inherits = __webpack_require__(22)
	  , JSON3 = __webpack_require__(43)
	  , utils = __webpack_require__(12)
	  , IframeTransport = __webpack_require__(42)
	  , InfoReceiverIframe = __webpack_require__(67)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:info-iframe');
	}

	function InfoIframe(baseUrl, url) {
	  var self = this;
	  EventEmitter.call(this);

	  var go = function() {
	    var ifr = self.ifr = new IframeTransport(InfoReceiverIframe.transportName, url, baseUrl);

	    ifr.once('message', function(msg) {
	      if (msg) {
	        var d;
	        try {
	          d = JSON3.parse(msg);
	        } catch (e) {
	          debug('bad json', msg);
	          self.emit('finish');
	          self.close();
	          return;
	        }

	        var info = d[0], rtt = d[1];
	        self.emit('finish', info, rtt);
	      }
	      self.close();
	    });

	    ifr.once('close', function() {
	      self.emit('finish');
	      self.close();
	    });
	  };

	  // TODO this seems the same as the 'needBody' from transports
	  if (!global.document.body) {
	    utils.attachEvent('load', go);
	  } else {
	    go();
	  }
	}

	inherits(InfoIframe, EventEmitter);

	InfoIframe.enabled = function() {
	  return IframeTransport.enabled();
	};

	InfoIframe.prototype.close = function() {
	  if (this.ifr) {
	    this.ifr.close();
	  }
	  this.removeAllListeners();
	  this.ifr = null;
	};

	module.exports = InfoIframe;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11), (function() { return this; }())))

/***/ },
/* 67 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var inherits = __webpack_require__(22)
	  , EventEmitter = __webpack_require__(23).EventEmitter
	  , JSON3 = __webpack_require__(43)
	  , XHRLocalObject = __webpack_require__(34)
	  , InfoAjax = __webpack_require__(68)
	  ;

	function InfoReceiverIframe(transUrl) {
	  var self = this;
	  EventEmitter.call(this);

	  this.ir = new InfoAjax(transUrl, XHRLocalObject);
	  this.ir.once('finish', function(info, rtt) {
	    self.ir = null;
	    self.emit('message', JSON3.stringify([info, rtt]));
	  });
	}

	inherits(InfoReceiverIframe, EventEmitter);

	InfoReceiverIframe.transportName = 'iframe-info-receiver';

	InfoReceiverIframe.prototype.close = function() {
	  if (this.ir) {
	    this.ir.close();
	    this.ir = null;
	  }
	  this.removeAllListeners();
	};

	module.exports = InfoReceiverIframe;


/***/ },
/* 68 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var EventEmitter = __webpack_require__(23).EventEmitter
	  , inherits = __webpack_require__(22)
	  , JSON3 = __webpack_require__(43)
	  , objectUtils = __webpack_require__(47)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:info-ajax');
	}

	function InfoAjax(url, AjaxObject) {
	  EventEmitter.call(this);

	  var self = this;
	  var t0 = +new Date();
	  this.xo = new AjaxObject('GET', url);

	  this.xo.once('finish', function(status, text) {
	    var info, rtt;
	    if (status === 200) {
	      rtt = (+new Date()) - t0;
	      if (text) {
	        try {
	          info = JSON3.parse(text);
	        } catch (e) {
	          debug('bad json', text);
	        }
	      }

	      if (!objectUtils.isObject(info)) {
	        info = {};
	      }
	    }
	    self.emit('finish', info, rtt);
	    self.removeAllListeners();
	  });
	}

	inherits(InfoAjax, EventEmitter);

	InfoAjax.prototype.close = function() {
	  this.removeAllListeners();
	  this.xo.close();
	};

	module.exports = InfoAjax;

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 69 */
/***/ function(module, exports, __webpack_require__) {

	/* WEBPACK VAR INJECTION */(function(process) {'use strict';

	var urlUtils = __webpack_require__(15)
	  , eventUtils = __webpack_require__(12)
	  , JSON3 = __webpack_require__(43)
	  , FacadeJS = __webpack_require__(70)
	  , InfoIframeReceiver = __webpack_require__(67)
	  , iframeUtils = __webpack_require__(46)
	  , loc = __webpack_require__(61)
	  ;

	var debug = function() {};
	if (process.env.NODE_ENV !== 'production') {
	  debug = __webpack_require__(19)('sockjs-client:iframe-bootstrap');
	}

	module.exports = function(SockJS, availableTransports) {
	  var transportMap = {};
	  availableTransports.forEach(function(at) {
	    if (at.facadeTransport) {
	      transportMap[at.facadeTransport.transportName] = at.facadeTransport;
	    }
	  });

	  // hard-coded for the info iframe
	  // TODO see if we can make this more dynamic
	  transportMap[InfoIframeReceiver.transportName] = InfoIframeReceiver;
	  var parentOrigin;

	  /* eslint-disable camelcase */
	  SockJS.bootstrap_iframe = function() {
	    /* eslint-enable camelcase */
	    var facade;
	    iframeUtils.currentWindowId = loc.hash.slice(1);
	    var onMessage = function(e) {
	      if (e.source !== parent) {
	        return;
	      }
	      if (typeof parentOrigin === 'undefined') {
	        parentOrigin = e.origin;
	      }
	      if (e.origin !== parentOrigin) {
	        return;
	      }

	      var iframeMessage;
	      try {
	        iframeMessage = JSON3.parse(e.data);
	      } catch (ignored) {
	        debug('bad json', e.data);
	        return;
	      }

	      if (iframeMessage.windowId !== iframeUtils.currentWindowId) {
	        return;
	      }
	      switch (iframeMessage.type) {
	      case 's':
	        var p;
	        try {
	          p = JSON3.parse(iframeMessage.data);
	        } catch (ignored) {
	          debug('bad json', iframeMessage.data);
	          break;
	        }
	        var version = p[0];
	        var transport = p[1];
	        var transUrl = p[2];
	        var baseUrl = p[3];
	        debug(version, transport, transUrl, baseUrl);
	        // change this to semver logic
	        if (version !== SockJS.version) {
	          throw new Error('Incompatible SockJS! Main site uses:' +
	                    ' "' + version + '", the iframe:' +
	                    ' "' + SockJS.version + '".');
	        }

	        if (!urlUtils.isOriginEqual(transUrl, loc.href) ||
	            !urlUtils.isOriginEqual(baseUrl, loc.href)) {
	          throw new Error('Can\'t connect to different domain from within an ' +
	                    'iframe. (' + loc.href + ', ' + transUrl + ', ' + baseUrl + ')');
	        }
	        facade = new FacadeJS(new transportMap[transport](transUrl, baseUrl));
	        break;
	      case 'm':
	        facade._send(iframeMessage.data);
	        break;
	      case 'c':
	        if (facade) {
	          facade._close();
	        }
	        facade = null;
	        break;
	      }
	    };

	    eventUtils.attachEvent('message', onMessage);

	    // Start
	    iframeUtils.postMessage('s');
	  };
	};

	/* WEBPACK VAR INJECTION */}.call(exports, __webpack_require__(11)))

/***/ },
/* 70 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var JSON3 = __webpack_require__(43)
	  , iframeUtils = __webpack_require__(46)
	  ;

	function FacadeJS(transport) {
	  this._transport = transport;
	  transport.on('message', this._transportMessage.bind(this));
	  transport.on('close', this._transportClose.bind(this));
	}

	FacadeJS.prototype._transportClose = function(code, reason) {
	  iframeUtils.postMessage('c', JSON3.stringify([code, reason]));
	};
	FacadeJS.prototype._transportMessage = function(frame) {
	  iframeUtils.postMessage('t', frame);
	};
	FacadeJS.prototype._send = function(data) {
	  this._transport.send(data);
	};
	FacadeJS.prototype._close = function() {
	  this._transport.close();
	  this._transport.removeAllListeners();
	};

	module.exports = FacadeJS;


/***/ },
/* 71 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';
	var ansiRegex = __webpack_require__(72)();

	module.exports = function (str) {
		return typeof str === 'string' ? str.replace(ansiRegex, '') : str;
	};


/***/ },
/* 72 */
/***/ function(module, exports) {

	'use strict';
	module.exports = function () {
		return /[\u001b\u009b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-PRZcf-nqry=><]/g;
	};


/***/ },
/* 73 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	var _slicedToArray = function () { function sliceIterator(arr, i) { var _arr = []; var _n = true; var _d = false; var _e = undefined; try { for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"]) _i["return"](); } finally { if (_d) throw _e; } } return _arr; } return function (arr, i) { if (Array.isArray(arr)) { return arr; } else if (Symbol.iterator in Object(arr)) { return sliceIterator(arr, i); } else { throw new TypeError("Invalid attempt to destructure non-iterable instance"); } }; }();

	var _twitterText = __webpack_require__(74);

	var _twitterText2 = _interopRequireDefault(_twitterText);

	var _ = __webpack_require__(75);

	var _2 = _interopRequireDefault(_);

	var _initColorPicker = __webpack_require__(116);

	var _initColorPicker2 = _interopRequireDefault(_initColorPicker);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var UI = _2.default.UI;

	var documentId = 'example.pdf';
	var PAGE_HEIGHT = void 0;
	var RENDER_OPTIONS = {
	  documentId: documentId,
	  pdfDocument: null,
	  scale: parseFloat(localStorage.getItem(documentId + '/scale'), 10) || 1.33,
	  rotate: parseInt(localStorage.getItem(documentId + '/rotate'), 10) || 0
	};

	_2.default.setStoreAdapter(new _2.default.LocalStoreAdapter());
	PDFJS.workerSrc = './shared/pdf.worker.js';

	// Render stuff
	var NUM_PAGES = 0;
	var renderedPages = [];
	var okToRender = false;
	document.getElementById('content-wrapper').addEventListener('scroll', function (e) {
	  var visiblePageNum = Math.round(e.target.scrollTop / PAGE_HEIGHT) + 1;
	  var visiblePage = document.querySelector('.page[data-page-number="' + visiblePageNum + '"][data-loaded="false"]');

	  if (renderedPages.indexOf(visiblePageNum) == -1) {
	    okToRender = true;
	    renderedPages.push(visiblePageNum);
	  } else {
	    okToRender = false;
	  }

	  if (visiblePage && okToRender) {
	    setTimeout(function () {
	      UI.renderPage(visiblePageNum, RENDER_OPTIONS);
	    });
	  }
	});

	function render() {
	  var url = buildUrl({ 'component': 'misc', 'page': 'base64_encode_pdf' });
	  var pdf64 = "data:application/pdf;base64,";
	  var pdfData;
	  var pdfData = atob(
  'JVBERi0xLjcKCjEgMCBvYmogICUgZW50cnkgcG9pbnQKPDwKICAvVHlwZSAvQ2F0YWxvZwog' +
  'IC9QYWdlcyAyIDAgUgo+PgplbmRvYmoKCjIgMCBvYmoKPDwKICAvVHlwZSAvUGFnZXMKICAv' +
  'TWVkaWFCb3ggWyAwIDAgMjAwIDIwMCBdCiAgL0NvdW50IDEKICAvS2lkcyBbIDMgMCBSIF0K' +
  'Pj4KZW5kb2JqCgozIDAgb2JqCjw8CiAgL1R5cGUgL1BhZ2UKICAvUGFyZW50IDIgMCBSCiAg' +
  'L1Jlc291cmNlcyA8PAogICAgL0ZvbnQgPDwKICAgICAgL0YxIDQgMCBSIAogICAgPj4KICA+' +
  'PgogIC9Db250ZW50cyA1IDAgUgo+PgplbmRvYmoKCjQgMCBvYmoKPDwKICAvVHlwZSAvRm9u' +
  'dAogIC9TdWJ0eXBlIC9UeXBlMQogIC9CYXNlRm9udCAvVGltZXMtUm9tYW4KPj4KZW5kb2Jq' +
  'Cgo1IDAgb2JqICAlIHBhZ2UgY29udGVudAo8PAogIC9MZW5ndGggNDQKPj4Kc3RyZWFtCkJU' +
  'CjcwIDUwIFRECi9GMSAxMiBUZgooSGVsbG8sIHdvcmxkISkgVGoKRVQKZW5kc3RyZWFtCmVu' +
  'ZG9iagoKeHJlZgowIDYKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwMDEwIDAwMDAwIG4g' +
  'CjAwMDAwMDAwNzkgMDAwMDAgbiAKMDAwMDAwMDE3MyAwMDAwMCBuIAowMDAwMDAwMzAxIDAw' +
  'MDAwIG4gCjAwMDAwMDAzODAgMDAwMDAgbiAKdHJhaWxlcgo8PAogIC9TaXplIDYKICAvUm9v' +
  'dCAxIDAgUgo+PgpzdGFydHhyZWYKNDkyCiUlRU9G');  
	PDFJS.getDocument({data:pdfData}).then(function (pdf) {
	    RENDER_OPTIONS.pdfDocument = pdf;

	    var viewer = document.getElementById('viewer');
	    viewer.innerHTML = '';
	    NUM_PAGES = pdf.pdfInfo.numPages;
	    for (var i = 0; i < NUM_PAGES; i++) {
	      var page = UI.createPage(i + 1);
	      viewer.appendChild(page);
	    }

	    UI.renderPage(1, RENDER_OPTIONS).then(function (_ref) {
	      var _ref2 = _slicedToArray(_ref, 2),
	          pdfPage = _ref2[0],
	          annotations = _ref2[1];

	      var viewport = pdfPage.getViewport(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate);
	      PAGE_HEIGHT = viewport.height;
	    });
	  }).catch(function(e){
              console.log(e);
          });
	}
	render();

	// Hotspot color stuff
	(function () {
	  var hotspotColor = localStorage.getItem(RENDER_OPTIONS.documentId + '/hotspot/color') || 'darkgoldenrod';
	  var currentTarget = undefined;

	  function handleAnnotationClick(target) {
	    var type = target.getAttribute('data-pdf-annotate-type');
	    if (['fillcircle', 'arrow'].indexOf(type) === -1) {
	      return; // nothing to do
	    }
	    currentTarget = target;
	    hotspotColor = currentTarget.getAttribute('stroke');

	    UI.setArrow(10, hotspotColor);
	    UI.setCircle(10, hotspotColor);

	    var a = document.querySelector('.hotspot-color .color');
	    if (a) {
	      a.setAttribute('data-color', hotspotColor);
	      a.style.background = hotspotColor;
	    }
	  }

	  function handleAnnotationBlur(target) {
	    if (currentTarget === target) {
	      currentTarget = undefined;
	    }
	  }

	  (0, _initColorPicker2.default)(document.querySelector('.hotspot-color'), hotspotColor, function (value) {
	    if (value === hotspotColor) {
	      return; // nothing to do     
	    }
	    localStorage.setItem(RENDER_OPTIONS.documentId + '/hotspot/color', value);
	    hotspotColor = value;

	    UI.setArrow(10, hotspotColor);
	    UI.setCircle(10, hotspotColor);

	    if (!currentTarget) {
	      return; // nothing to do
	    }

	    var type = currentTarget.getAttribute('data-pdf-annotate-type');
	    var annotationId = currentTarget.getAttribute('data-pdf-annotate-id');
	    if (['fillcircle', 'arrow'].indexOf(type) === -1) {
	      return; // nothing to do
	    }

	    // update target
	    currentTarget.setAttribute('stroke', hotspotColor);
	    currentTarget.setAttribute('fill', hotspotColor);

	    // update annotation
	    _2.default.getStoreAdapter().getAnnotation(documentId, annotationId).then(function (annotation) {
	      annotation.color = hotspotColor;
	      _2.default.getStoreAdapter().editAnnotation(documentId, annotationId, annotation);
	    });
	  });

	  UI.addEventListener('annotation:click', handleAnnotationClick);
	  UI.addEventListener('annotation:blur', handleAnnotationBlur);
	})();

	// Text stuff
	(function () {
	  var textSize = void 0;
	  var textColor = void 0;

	  function initText() {
	    var size = document.querySelector('.toolbar .text-size');
	    [8, 9, 10, 11, 12, 14, 18, 24, 30, 36, 48, 60, 72, 96].forEach(function (s) {
	      size.appendChild(new Option(s, s));
	    });

	    setText(localStorage.getItem(RENDER_OPTIONS.documentId + '/text/size') || 10, localStorage.getItem(RENDER_OPTIONS.documentId + '/text/color') || '#000000');

	    (0, _initColorPicker2.default)(document.querySelector('.text-color'), textColor, function (value) {
	      setText(textSize, value);
	    });
	  }

	  function setText(size, color) {
	    var modified = false;

	    if (textSize !== size) {
	      modified = true;
	      textSize = size;
	      localStorage.setItem(RENDER_OPTIONS.documentId + '/text/size', textSize);
	      document.querySelector('.toolbar .text-size').value = textSize;
	    }

	    if (textColor !== color) {
	      modified = true;
	      textColor = color;
	      localStorage.setItem(RENDER_OPTIONS.documentId + '/text/color', textColor);

	      var selected = document.querySelector('.toolbar .text-color.color-selected');
	      if (selected) {
	        selected.classList.remove('color-selected');
	        selected.removeAttribute('aria-selected');
	      }

	      selected = document.querySelector('.toolbar .text-color[data-color="' + color + '"]');
	      if (selected) {
	        selected.classList.add('color-selected');
	        selected.setAttribute('aria-selected', true);
	      }
	    }

	    if (modified) {
	      UI.setText(textSize, textColor);
	    }
	  }

	  function handleTextSizeChange(e) {
	    setText(e.target.value, textColor);
	  }

	  document.querySelector('.toolbar .text-size').addEventListener('change', handleTextSizeChange);

	  initText();
	})();

	// Pen stuff
	(function () {
	  var penSize = void 0;
	  var penColor = void 0;

	  function initPen() {
	    var size = document.querySelector('.toolbar .pen-size');
	    for (var i = 0; i < 20; i++) {
	      size.appendChild(new Option(i + 1, i + 1));
	    }

	    setPen(localStorage.getItem(RENDER_OPTIONS.documentId + '/pen/size') || 1, localStorage.getItem(RENDER_OPTIONS.documentId + '/pen/color') || '#000000');

	    (0, _initColorPicker2.default)(document.querySelector('.pen-color'), penColor, function (value) {
	      setPen(penSize, value);
	    });
	  }

	  function setPen(size, color) {
	    var modified = false;

	    if (penSize !== size) {
	      modified = true;
	      penSize = size;
	      localStorage.setItem(RENDER_OPTIONS.documentId + '/pen/size', penSize);
	      document.querySelector('.toolbar .pen-size').value = penSize;
	    }

	    if (penColor !== color) {
	      modified = true;
	      penColor = color;
	      localStorage.setItem(RENDER_OPTIONS.documentId + '/pen/color', penColor);

	      var selected = document.querySelector('.toolbar .pen-color.color-selected');
	      if (selected) {
	        selected.classList.remove('color-selected');
	        selected.removeAttribute('aria-selected');
	      }

	      selected = document.querySelector('.toolbar .pen-color[data-color="' + color + '"]');
	      if (selected) {
	        selected.classList.add('color-selected');
	        selected.setAttribute('aria-selected', true);
	      }
	    }

	    if (modified) {
	      UI.setPen(penSize, penColor);
	    }
	  }

	  function handlePenSizeChange(e) {
	    setPen(e.target.value, penColor);
	  }

	  document.querySelector('.toolbar .pen-size').addEventListener('change', handlePenSizeChange);

	  initPen();
	})();

	// Toolbar buttons
	(function () {
	  var tooltype = localStorage.getItem(RENDER_OPTIONS.documentId + '/tooltype') || 'cursor';
	  if (tooltype) {
	    setActiveToolbarItem(tooltype, document.querySelector('.toolbar button[data-tooltype=' + tooltype + ']'));
	  }

	  function setActiveToolbarItem(type, button) {
	    var active = document.querySelector('.toolbar button.active');
	    if (active) {
	      active.classList.remove('active');

	      switch (tooltype) {
	        case 'cursor':
	          UI.disableEdit();
	          break;
	        case 'draw':
	          UI.disablePen();
	          break;
	        case 'arrow':
	          UI.disableArrow();
	          break;
	        case 'text':
	          UI.disableText();
	          break;
	        case 'point':
	          UI.disablePoint();
	          break;
	        case 'area':
	        case 'highlight':
	        case 'strikeout':
	          UI.disableRect();
	          break;
	        case 'circle':
	        case 'emptycircle':
	        case 'fillcircle':
	          UI.disableCircle();
	          break;
	      }
	    }

	    if (button) {
	      button.classList.add('active');
	    }
	    if (tooltype !== type) {
	      localStorage.setItem(RENDER_OPTIONS.documentId + '/tooltype', type);
	    }
	    tooltype = type;

	    switch (type) {
	      case 'cursor':
	        UI.enableEdit();
	        break;
	      case 'draw':
	        UI.enablePen();
	        break;
	      case 'arrow':
	        UI.enableArrow();
	        break;
	      case 'text':
	        UI.enableText();
	        break;
	      case 'point':
	        UI.enablePoint();
	        break;
	      case 'area':
	      case 'highlight':
	      case 'strikeout':
	        UI.enableRect(type);
	        break;
	      case 'circle':
	      case 'emptycircle':
	      case 'fillcircle':
	        UI.enableCircle(type);
	        break;
	    }
	  }

	  function handleToolbarClick(e) {
	    if (e.target.nodeName === 'BUTTON') {
	      setActiveToolbarItem(e.target.getAttribute('data-tooltype'), e.target);
	    }
	  }

	  document.querySelector('.toolbar').addEventListener('click', handleToolbarClick);
	})();

	// Scale/rotate
	(function () {
	  function setScaleRotate(scale, rotate) {
	    scale = parseFloat(scale, 10);
	    rotate = parseInt(rotate, 10);

	    if (RENDER_OPTIONS.scale !== scale || RENDER_OPTIONS.rotate !== rotate) {
	      RENDER_OPTIONS.scale = scale;
	      RENDER_OPTIONS.rotate = rotate;

	      localStorage.setItem(RENDER_OPTIONS.documentId + '/scale', RENDER_OPTIONS.scale);
	      localStorage.setItem(RENDER_OPTIONS.documentId + '/rotate', RENDER_OPTIONS.rotate % 360);

	      render();
	    }
	  }

	  function handleScaleChange(e) {
	    setScaleRotate(e.target.value, RENDER_OPTIONS.rotate);
	  }

	  function handleRotateCWClick() {
	    setScaleRotate(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate + 90);
	  }

	  function handleRotateCCWClick() {
	    setScaleRotate(RENDER_OPTIONS.scale, RENDER_OPTIONS.rotate - 90);
	  }

	  document.querySelector('.toolbar select.scale').value = RENDER_OPTIONS.scale;
	  document.querySelector('.toolbar select.scale').addEventListener('change', handleScaleChange);
	  document.querySelector('.toolbar .rotate-ccw').addEventListener('click', handleRotateCCWClick);
	  document.querySelector('.toolbar .rotate-cw').addEventListener('click', handleRotateCWClick);
	})();

	// Clear toolbar button
	(function () {
	  function handleClearClick(e) {
	    if (confirm('Are you sure you want to clear annotations?')) {
	      for (var i = 0; i < NUM_PAGES; i++) {
	        document.querySelector('div#pageContainer' + (i + 1) + ' svg.annotationLayer').innerHTML = '';
	      }

	      localStorage.removeItem(RENDER_OPTIONS.documentId + '/annotations');
	    }
	  }
	  document.querySelector('a.clear').addEventListener('click', handleClearClick);
	})();

	// Comment stuff
	(function (window, document) {
	  var commentList = document.querySelector('#comment-wrapper .comment-list-container');
	  var commentForm = document.querySelector('#comment-wrapper .comment-list-form');
	  var commentText = commentForm.querySelector('input[type="text"]');

	  function supportsComments(target) {
	    var type = target.getAttribute('data-pdf-annotate-type');
	    return ['point', 'highlight', 'area'].indexOf(type) > -1;
	  }

	  function insertComment(comment) {
	    var child = document.createElement('div');
	    child.className = 'comment-list-item';
	    child.innerHTML = _twitterText2.default.autoLink(_twitterText2.default.htmlEscape(comment.content));

	    commentList.appendChild(child);
	  }

	  function handleAnnotationClick(target) {
	    if (supportsComments(target)) {
	      var _documentId = target.parentNode.getAttribute('data-pdf-annotate-document');
	      var annotationId = target.getAttribute('data-pdf-annotate-id');

	      _2.default.getStoreAdapter().getComments(_documentId, annotationId).then(function (comments) {
	        commentList.innerHTML = '';
	        commentForm.style.display = '';
	        commentText.focus();

	        commentForm.onsubmit = function () {
	          _2.default.getStoreAdapter().addComment(_documentId, annotationId, commentText.value.trim()).then(insertComment).then(function () {
	            commentText.value = '';
	            commentText.focus();
	          });

	          return false;
	        };

	        comments.forEach(insertComment);
	      });
	    }
	  }

	  function handleAnnotationBlur(target) {
	    if (supportsComments(target)) {
	      commentList.innerHTML = '';
	      commentForm.style.display = 'none';
	      commentForm.onsubmit = null;

	      insertComment({ content: 'No comments' });
	    }
	  }

	  UI.addEventListener('annotation:click', handleAnnotationClick);
	  UI.addEventListener('annotation:blur', handleAnnotationBlur);

	  UI.setArrow(10, 'darkgoldenrod');
	  UI.setCircle(10, 'darkgoldenrod');
	})(window, document);

	function buildUrl(parts) {
	  var constructed = "";
	  for (var part in parts) {
	    if (parts.hasOwnProperty(part)) {
	      constructed += "&" + part + "=" + parts[part];
	    }
	  }
	  return document.body.dataset.siteUrl + constructed;
	}

	function convertDataURIToBinary(dataURI) {
          var BASE64_MARKER = ';base64,';
	  var base64Index = dataURI.indexOf(BASE64_MARKER) + BASE64_MARKER.length;
	  var base64 = dataURI.substring(base64Index);
	  var raw = window.atob(base64);
	  var rawLength = raw.length;
	  var array = new Uint8Array(new ArrayBuffer(rawLength));

	  for (var i = 0; i < rawLength; i++) {
	    array[i] = raw.charCodeAt(i);
	  }
	  return array;
	}

/***/ },
/* 74 */
/***/ function(module, exports, __webpack_require__) {

	var __WEBPACK_AMD_DEFINE_FACTORY__, __WEBPACK_AMD_DEFINE_ARRAY__, __WEBPACK_AMD_DEFINE_RESULT__;(function() {
	  if (typeof twttr === "undefined" || twttr === null) {
	    var twttr = {};
	  }

	  twttr.txt = {};
	  twttr.txt.regexen = {};

	  var HTML_ENTITIES = {
	    '&': '&amp;',
	    '>': '&gt;',
	    '<': '&lt;',
	    '"': '&quot;',
	    "'": '&#39;'
	  };

	  // HTML escaping
	  twttr.txt.htmlEscape = function(text) {
	    return text && text.replace(/[&"'><]/g, function(character) {
	      return HTML_ENTITIES[character];
	    });
	  };

	  // Builds a RegExp
	  function regexSupplant(regex, flags) {
	    flags = flags || "";
	    if (typeof regex !== "string") {
	      if (regex.global && flags.indexOf("g") < 0) {
	        flags += "g";
	      }
	      if (regex.ignoreCase && flags.indexOf("i") < 0) {
	        flags += "i";
	      }
	      if (regex.multiline && flags.indexOf("m") < 0) {
	        flags += "m";
	      }

	      regex = regex.source;
	    }

	    return new RegExp(regex.replace(/#\{(\w+)\}/g, function(match, name) {
	      var newRegex = twttr.txt.regexen[name] || "";
	      if (typeof newRegex !== "string") {
	        newRegex = newRegex.source;
	      }
	      return newRegex;
	    }), flags);
	  }

	  twttr.txt.regexSupplant = regexSupplant;

	  // simple string interpolation
	  function stringSupplant(str, values) {
	    return str.replace(/#\{(\w+)\}/g, function(match, name) {
	      return values[name] || "";
	    });
	  }

	  twttr.txt.stringSupplant = stringSupplant;

	  twttr.txt.regexen.spaces_group = /\x09-\x0D\x20\x85\xA0\u1680\u180E\u2000-\u200A\u2028\u2029\u202F\u205F\u3000/;
	  twttr.txt.regexen.spaces = regexSupplant(/[#{spaces_group}]/);
	  twttr.txt.regexen.invalid_chars_group = /\uFFFE\uFEFF\uFFFF\u202A-\u202E/;
	  twttr.txt.regexen.invalid_chars = regexSupplant(/[#{invalid_chars_group}]/);
	  twttr.txt.regexen.punct = /\!'#%&'\(\)*\+,\\\-\.\/:;<=>\?@\[\]\^_{|}~\$/;
	  twttr.txt.regexen.rtl_chars = /[\u0600-\u06FF]|[\u0750-\u077F]|[\u0590-\u05FF]|[\uFE70-\uFEFF]/mg;
	  twttr.txt.regexen.non_bmp_code_pairs = /[\uD800-\uDBFF][\uDC00-\uDFFF]/mg;

	  twttr.txt.regexen.latinAccentChars = /\xC0-\xD6\xD8-\xF6\xF8-\xFF\u0100-\u024F\u0253\u0254\u0256\u0257\u0259\u025B\u0263\u0268\u026F\u0272\u0289\u028B\u02BB\u0300-\u036F\u1E00-\u1EFF/;

	  // Generated from unicode_regex/unicode_regex_groups.scala, same as objective c's \p{L}\p{M}
	  twttr.txt.regexen.bmpLetterAndMarks = /A-Za-z\xaa\xb5\xba\xc0-\xd6\xd8-\xf6\xf8-\u02c1\u02c6-\u02d1\u02e0-\u02e4\u02ec\u02ee\u0300-\u0374\u0376\u0377\u037a-\u037d\u037f\u0386\u0388-\u038a\u038c\u038e-\u03a1\u03a3-\u03f5\u03f7-\u0481\u0483-\u052f\u0531-\u0556\u0559\u0561-\u0587\u0591-\u05bd\u05bf\u05c1\u05c2\u05c4\u05c5\u05c7\u05d0-\u05ea\u05f0-\u05f2\u0610-\u061a\u0620-\u065f\u066e-\u06d3\u06d5-\u06dc\u06df-\u06e8\u06ea-\u06ef\u06fa-\u06fc\u06ff\u0710-\u074a\u074d-\u07b1\u07ca-\u07f5\u07fa\u0800-\u082d\u0840-\u085b\u08a0-\u08b2\u08e4-\u0963\u0971-\u0983\u0985-\u098c\u098f\u0990\u0993-\u09a8\u09aa-\u09b0\u09b2\u09b6-\u09b9\u09bc-\u09c4\u09c7\u09c8\u09cb-\u09ce\u09d7\u09dc\u09dd\u09df-\u09e3\u09f0\u09f1\u0a01-\u0a03\u0a05-\u0a0a\u0a0f\u0a10\u0a13-\u0a28\u0a2a-\u0a30\u0a32\u0a33\u0a35\u0a36\u0a38\u0a39\u0a3c\u0a3e-\u0a42\u0a47\u0a48\u0a4b-\u0a4d\u0a51\u0a59-\u0a5c\u0a5e\u0a70-\u0a75\u0a81-\u0a83\u0a85-\u0a8d\u0a8f-\u0a91\u0a93-\u0aa8\u0aaa-\u0ab0\u0ab2\u0ab3\u0ab5-\u0ab9\u0abc-\u0ac5\u0ac7-\u0ac9\u0acb-\u0acd\u0ad0\u0ae0-\u0ae3\u0b01-\u0b03\u0b05-\u0b0c\u0b0f\u0b10\u0b13-\u0b28\u0b2a-\u0b30\u0b32\u0b33\u0b35-\u0b39\u0b3c-\u0b44\u0b47\u0b48\u0b4b-\u0b4d\u0b56\u0b57\u0b5c\u0b5d\u0b5f-\u0b63\u0b71\u0b82\u0b83\u0b85-\u0b8a\u0b8e-\u0b90\u0b92-\u0b95\u0b99\u0b9a\u0b9c\u0b9e\u0b9f\u0ba3\u0ba4\u0ba8-\u0baa\u0bae-\u0bb9\u0bbe-\u0bc2\u0bc6-\u0bc8\u0bca-\u0bcd\u0bd0\u0bd7\u0c00-\u0c03\u0c05-\u0c0c\u0c0e-\u0c10\u0c12-\u0c28\u0c2a-\u0c39\u0c3d-\u0c44\u0c46-\u0c48\u0c4a-\u0c4d\u0c55\u0c56\u0c58\u0c59\u0c60-\u0c63\u0c81-\u0c83\u0c85-\u0c8c\u0c8e-\u0c90\u0c92-\u0ca8\u0caa-\u0cb3\u0cb5-\u0cb9\u0cbc-\u0cc4\u0cc6-\u0cc8\u0cca-\u0ccd\u0cd5\u0cd6\u0cde\u0ce0-\u0ce3\u0cf1\u0cf2\u0d01-\u0d03\u0d05-\u0d0c\u0d0e-\u0d10\u0d12-\u0d3a\u0d3d-\u0d44\u0d46-\u0d48\u0d4a-\u0d4e\u0d57\u0d60-\u0d63\u0d7a-\u0d7f\u0d82\u0d83\u0d85-\u0d96\u0d9a-\u0db1\u0db3-\u0dbb\u0dbd\u0dc0-\u0dc6\u0dca\u0dcf-\u0dd4\u0dd6\u0dd8-\u0ddf\u0df2\u0df3\u0e01-\u0e3a\u0e40-\u0e4e\u0e81\u0e82\u0e84\u0e87\u0e88\u0e8a\u0e8d\u0e94-\u0e97\u0e99-\u0e9f\u0ea1-\u0ea3\u0ea5\u0ea7\u0eaa\u0eab\u0ead-\u0eb9\u0ebb-\u0ebd\u0ec0-\u0ec4\u0ec6\u0ec8-\u0ecd\u0edc-\u0edf\u0f00\u0f18\u0f19\u0f35\u0f37\u0f39\u0f3e-\u0f47\u0f49-\u0f6c\u0f71-\u0f84\u0f86-\u0f97\u0f99-\u0fbc\u0fc6\u1000-\u103f\u1050-\u108f\u109a-\u109d\u10a0-\u10c5\u10c7\u10cd\u10d0-\u10fa\u10fc-\u1248\u124a-\u124d\u1250-\u1256\u1258\u125a-\u125d\u1260-\u1288\u128a-\u128d\u1290-\u12b0\u12b2-\u12b5\u12b8-\u12be\u12c0\u12c2-\u12c5\u12c8-\u12d6\u12d8-\u1310\u1312-\u1315\u1318-\u135a\u135d-\u135f\u1380-\u138f\u13a0-\u13f4\u1401-\u166c\u166f-\u167f\u1681-\u169a\u16a0-\u16ea\u16f1-\u16f8\u1700-\u170c\u170e-\u1714\u1720-\u1734\u1740-\u1753\u1760-\u176c\u176e-\u1770\u1772\u1773\u1780-\u17d3\u17d7\u17dc\u17dd\u180b-\u180d\u1820-\u1877\u1880-\u18aa\u18b0-\u18f5\u1900-\u191e\u1920-\u192b\u1930-\u193b\u1950-\u196d\u1970-\u1974\u1980-\u19ab\u19b0-\u19c9\u1a00-\u1a1b\u1a20-\u1a5e\u1a60-\u1a7c\u1a7f\u1aa7\u1ab0-\u1abe\u1b00-\u1b4b\u1b6b-\u1b73\u1b80-\u1baf\u1bba-\u1bf3\u1c00-\u1c37\u1c4d-\u1c4f\u1c5a-\u1c7d\u1cd0-\u1cd2\u1cd4-\u1cf6\u1cf8\u1cf9\u1d00-\u1df5\u1dfc-\u1f15\u1f18-\u1f1d\u1f20-\u1f45\u1f48-\u1f4d\u1f50-\u1f57\u1f59\u1f5b\u1f5d\u1f5f-\u1f7d\u1f80-\u1fb4\u1fb6-\u1fbc\u1fbe\u1fc2-\u1fc4\u1fc6-\u1fcc\u1fd0-\u1fd3\u1fd6-\u1fdb\u1fe0-\u1fec\u1ff2-\u1ff4\u1ff6-\u1ffc\u2071\u207f\u2090-\u209c\u20d0-\u20f0\u2102\u2107\u210a-\u2113\u2115\u2119-\u211d\u2124\u2126\u2128\u212a-\u212d\u212f-\u2139\u213c-\u213f\u2145-\u2149\u214e\u2183\u2184\u2c00-\u2c2e\u2c30-\u2c5e\u2c60-\u2ce4\u2ceb-\u2cf3\u2d00-\u2d25\u2d27\u2d2d\u2d30-\u2d67\u2d6f\u2d7f-\u2d96\u2da0-\u2da6\u2da8-\u2dae\u2db0-\u2db6\u2db8-\u2dbe\u2dc0-\u2dc6\u2dc8-\u2dce\u2dd0-\u2dd6\u2dd8-\u2dde\u2de0-\u2dff\u2e2f\u3005\u3006\u302a-\u302f\u3031-\u3035\u303b\u303c\u3041-\u3096\u3099\u309a\u309d-\u309f\u30a1-\u30fa\u30fc-\u30ff\u3105-\u312d\u3131-\u318e\u31a0-\u31ba\u31f0-\u31ff\u3400-\u4db5\u4e00-\u9fcc\ua000-\ua48c\ua4d0-\ua4fd\ua500-\ua60c\ua610-\ua61f\ua62a\ua62b\ua640-\ua672\ua674-\ua67d\ua67f-\ua69d\ua69f-\ua6e5\ua6f0\ua6f1\ua717-\ua71f\ua722-\ua788\ua78b-\ua78e\ua790-\ua7ad\ua7b0\ua7b1\ua7f7-\ua827\ua840-\ua873\ua880-\ua8c4\ua8e0-\ua8f7\ua8fb\ua90a-\ua92d\ua930-\ua953\ua960-\ua97c\ua980-\ua9c0\ua9cf\ua9e0-\ua9ef\ua9fa-\ua9fe\uaa00-\uaa36\uaa40-\uaa4d\uaa60-\uaa76\uaa7a-\uaac2\uaadb-\uaadd\uaae0-\uaaef\uaaf2-\uaaf6\uab01-\uab06\uab09-\uab0e\uab11-\uab16\uab20-\uab26\uab28-\uab2e\uab30-\uab5a\uab5c-\uab5f\uab64\uab65\uabc0-\uabea\uabec\uabed\uac00-\ud7a3\ud7b0-\ud7c6\ud7cb-\ud7fb\uf870-\uf87f\uf882\uf884-\uf89f\uf8b8\uf8c1-\uf8d6\uf900-\ufa6d\ufa70-\ufad9\ufb00-\ufb06\ufb13-\ufb17\ufb1d-\ufb28\ufb2a-\ufb36\ufb38-\ufb3c\ufb3e\ufb40\ufb41\ufb43\ufb44\ufb46-\ufbb1\ufbd3-\ufd3d\ufd50-\ufd8f\ufd92-\ufdc7\ufdf0-\ufdfb\ufe00-\ufe0f\ufe20-\ufe2d\ufe70-\ufe74\ufe76-\ufefc\uff21-\uff3a\uff41-\uff5a\uff66-\uffbe\uffc2-\uffc7\uffca-\uffcf\uffd2-\uffd7\uffda-\uffdc/;
	  twttr.txt.regexen.astralLetterAndMarks = /\ud800[\udc00-\udc0b\udc0d-\udc26\udc28-\udc3a\udc3c\udc3d\udc3f-\udc4d\udc50-\udc5d\udc80-\udcfa\uddfd\ude80-\ude9c\udea0-\uded0\udee0\udf00-\udf1f\udf30-\udf40\udf42-\udf49\udf50-\udf7a\udf80-\udf9d\udfa0-\udfc3\udfc8-\udfcf]|\ud801[\udc00-\udc9d\udd00-\udd27\udd30-\udd63\ude00-\udf36\udf40-\udf55\udf60-\udf67]|\ud802[\udc00-\udc05\udc08\udc0a-\udc35\udc37\udc38\udc3c\udc3f-\udc55\udc60-\udc76\udc80-\udc9e\udd00-\udd15\udd20-\udd39\udd80-\uddb7\uddbe\uddbf\ude00-\ude03\ude05\ude06\ude0c-\ude13\ude15-\ude17\ude19-\ude33\ude38-\ude3a\ude3f\ude60-\ude7c\ude80-\ude9c\udec0-\udec7\udec9-\udee6\udf00-\udf35\udf40-\udf55\udf60-\udf72\udf80-\udf91]|\ud803[\udc00-\udc48]|\ud804[\udc00-\udc46\udc7f-\udcba\udcd0-\udce8\udd00-\udd34\udd50-\udd73\udd76\udd80-\uddc4\uddda\ude00-\ude11\ude13-\ude37\udeb0-\udeea\udf01-\udf03\udf05-\udf0c\udf0f\udf10\udf13-\udf28\udf2a-\udf30\udf32\udf33\udf35-\udf39\udf3c-\udf44\udf47\udf48\udf4b-\udf4d\udf57\udf5d-\udf63\udf66-\udf6c\udf70-\udf74]|\ud805[\udc80-\udcc5\udcc7\udd80-\uddb5\uddb8-\uddc0\ude00-\ude40\ude44\ude80-\udeb7]|\ud806[\udca0-\udcdf\udcff\udec0-\udef8]|\ud808[\udc00-\udf98]|\ud80c[\udc00-\udfff]|\ud80d[\udc00-\udc2e]|\ud81a[\udc00-\ude38\ude40-\ude5e\uded0-\udeed\udef0-\udef4\udf00-\udf36\udf40-\udf43\udf63-\udf77\udf7d-\udf8f]|\ud81b[\udf00-\udf44\udf50-\udf7e\udf8f-\udf9f]|\ud82c[\udc00\udc01]|\ud82f[\udc00-\udc6a\udc70-\udc7c\udc80-\udc88\udc90-\udc99\udc9d\udc9e]|\ud834[\udd65-\udd69\udd6d-\udd72\udd7b-\udd82\udd85-\udd8b\uddaa-\uddad\ude42-\ude44]|\ud835[\udc00-\udc54\udc56-\udc9c\udc9e\udc9f\udca2\udca5\udca6\udca9-\udcac\udcae-\udcb9\udcbb\udcbd-\udcc3\udcc5-\udd05\udd07-\udd0a\udd0d-\udd14\udd16-\udd1c\udd1e-\udd39\udd3b-\udd3e\udd40-\udd44\udd46\udd4a-\udd50\udd52-\udea5\udea8-\udec0\udec2-\udeda\udedc-\udefa\udefc-\udf14\udf16-\udf34\udf36-\udf4e\udf50-\udf6e\udf70-\udf88\udf8a-\udfa8\udfaa-\udfc2\udfc4-\udfcb]|\ud83a[\udc00-\udcc4\udcd0-\udcd6]|\ud83b[\ude00-\ude03\ude05-\ude1f\ude21\ude22\ude24\ude27\ude29-\ude32\ude34-\ude37\ude39\ude3b\ude42\ude47\ude49\ude4b\ude4d-\ude4f\ude51\ude52\ude54\ude57\ude59\ude5b\ude5d\ude5f\ude61\ude62\ude64\ude67-\ude6a\ude6c-\ude72\ude74-\ude77\ude79-\ude7c\ude7e\ude80-\ude89\ude8b-\ude9b\udea1-\udea3\udea5-\udea9\udeab-\udebb]|\ud840[\udc00-\udfff]|\ud841[\udc00-\udfff]|\ud842[\udc00-\udfff]|\ud843[\udc00-\udfff]|\ud844[\udc00-\udfff]|\ud845[\udc00-\udfff]|\ud846[\udc00-\udfff]|\ud847[\udc00-\udfff]|\ud848[\udc00-\udfff]|\ud849[\udc00-\udfff]|\ud84a[\udc00-\udfff]|\ud84b[\udc00-\udfff]|\ud84c[\udc00-\udfff]|\ud84d[\udc00-\udfff]|\ud84e[\udc00-\udfff]|\ud84f[\udc00-\udfff]|\ud850[\udc00-\udfff]|\ud851[\udc00-\udfff]|\ud852[\udc00-\udfff]|\ud853[\udc00-\udfff]|\ud854[\udc00-\udfff]|\ud855[\udc00-\udfff]|\ud856[\udc00-\udfff]|\ud857[\udc00-\udfff]|\ud858[\udc00-\udfff]|\ud859[\udc00-\udfff]|\ud85a[\udc00-\udfff]|\ud85b[\udc00-\udfff]|\ud85c[\udc00-\udfff]|\ud85d[\udc00-\udfff]|\ud85e[\udc00-\udfff]|\ud85f[\udc00-\udfff]|\ud860[\udc00-\udfff]|\ud861[\udc00-\udfff]|\ud862[\udc00-\udfff]|\ud863[\udc00-\udfff]|\ud864[\udc00-\udfff]|\ud865[\udc00-\udfff]|\ud866[\udc00-\udfff]|\ud867[\udc00-\udfff]|\ud868[\udc00-\udfff]|\ud869[\udc00-\uded6\udf00-\udfff]|\ud86a[\udc00-\udfff]|\ud86b[\udc00-\udfff]|\ud86c[\udc00-\udfff]|\ud86d[\udc00-\udf34\udf40-\udfff]|\ud86e[\udc00-\udc1d]|\ud87e[\udc00-\ude1d]|\udb40[\udd00-\uddef]/;

	  // Generated from unicode_regex/unicode_regex_groups.scala, same as objective c's \p{Nd}
	  twttr.txt.regexen.bmpNumerals = /0-9\u0660-\u0669\u06f0-\u06f9\u07c0-\u07c9\u0966-\u096f\u09e6-\u09ef\u0a66-\u0a6f\u0ae6-\u0aef\u0b66-\u0b6f\u0be6-\u0bef\u0c66-\u0c6f\u0ce6-\u0cef\u0d66-\u0d6f\u0de6-\u0def\u0e50-\u0e59\u0ed0-\u0ed9\u0f20-\u0f29\u1040-\u1049\u1090-\u1099\u17e0-\u17e9\u1810-\u1819\u1946-\u194f\u19d0-\u19d9\u1a80-\u1a89\u1a90-\u1a99\u1b50-\u1b59\u1bb0-\u1bb9\u1c40-\u1c49\u1c50-\u1c59\ua620-\ua629\ua8d0-\ua8d9\ua900-\ua909\ua9d0-\ua9d9\ua9f0-\ua9f9\uaa50-\uaa59\uabf0-\uabf9\uff10-\uff19/;
	  twttr.txt.regexen.astralNumerals = /\ud801[\udca0-\udca9]|\ud804[\udc66-\udc6f\udcf0-\udcf9\udd36-\udd3f\uddd0-\uddd9\udef0-\udef9]|\ud805[\udcd0-\udcd9\ude50-\ude59\udec0-\udec9]|\ud806[\udce0-\udce9]|\ud81a[\ude60-\ude69\udf50-\udf59]|\ud835[\udfce-\udfff]/;

	  twttr.txt.regexen.hashtagSpecialChars = /_\u200c\u200d\ua67e\u05be\u05f3\u05f4\uff5e\u301c\u309b\u309c\u30a0\u30fb\u3003\u0f0b\u0f0c\xb7/;

	  // A hashtag must contain at least one unicode letter or mark, as well as numbers, underscores, and select special characters.
	  twttr.txt.regexen.hashSigns = /[#＃]/;
	  twttr.txt.regexen.hashtagAlpha = regexSupplant(/(?:[#{bmpLetterAndMarks}]|(?=#{non_bmp_code_pairs})(?:#{astralLetterAndMarks}))/);
	  twttr.txt.regexen.hashtagAlphaNumeric = regexSupplant(/(?:[#{bmpLetterAndMarks}#{bmpNumerals}#{hashtagSpecialChars}]|(?=#{non_bmp_code_pairs})(?:#{astralLetterAndMarks}|#{astralNumerals}))/);
	  twttr.txt.regexen.endHashtagMatch = regexSupplant(/^(?:#{hashSigns}|:\/\/)/);
	  twttr.txt.regexen.codePoint = /(?:[^\uD800-\uDFFF]|[\uD800-\uDBFF][\uDC00-\uDFFF])/;
	  twttr.txt.regexen.hashtagBoundary = regexSupplant(/(?:^|\uFE0E|\uFE0F|$|(?!#{hashtagAlphaNumeric}|&)#{codePoint})/);
	  twttr.txt.regexen.validHashtag = regexSupplant(/(#{hashtagBoundary})(#{hashSigns})(?!\uFE0F|\u20E3)(#{hashtagAlphaNumeric}*#{hashtagAlpha}#{hashtagAlphaNumeric}*)/gi);

	  // Mention related regex collection
	  twttr.txt.regexen.validMentionPrecedingChars = /(?:^|[^a-zA-Z0-9_!#$%&*@＠]|(?:^|[^a-zA-Z0-9_+~.-])(?:rt|RT|rT|Rt):?)/;
	  twttr.txt.regexen.atSigns = /[@＠]/;
	  twttr.txt.regexen.validMentionOrList = regexSupplant(
	    '(#{validMentionPrecedingChars})' +  // $1: Preceding character
	    '(#{atSigns})' +                     // $2: At mark
	    '([a-zA-Z0-9_]{1,20})' +             // $3: Screen name
	    '(\/[a-zA-Z][a-zA-Z0-9_\-]{0,24})?'  // $4: List (optional)
	  , 'g');
	  twttr.txt.regexen.validReply = regexSupplant(/^(?:#{spaces})*#{atSigns}([a-zA-Z0-9_]{1,20})/);
	  twttr.txt.regexen.endMentionMatch = regexSupplant(/^(?:#{atSigns}|[#{latinAccentChars}]|:\/\/)/);

	  // URL related regex collection
	  twttr.txt.regexen.validUrlPrecedingChars = regexSupplant(/(?:[^A-Za-z0-9@＠$#＃#{invalid_chars_group}]|^)/);
	  twttr.txt.regexen.invalidUrlWithoutProtocolPrecedingChars = /[-_.\/]$/;
	  twttr.txt.regexen.invalidDomainChars = stringSupplant("#{punct}#{spaces_group}#{invalid_chars_group}", twttr.txt.regexen);
	  twttr.txt.regexen.validDomainChars = regexSupplant(/[^#{invalidDomainChars}]/);
	  twttr.txt.regexen.validSubdomain = regexSupplant(/(?:(?:#{validDomainChars}(?:[_-]|#{validDomainChars})*)?#{validDomainChars}\.)/);
	  twttr.txt.regexen.validDomainName = regexSupplant(/(?:(?:#{validDomainChars}(?:-|#{validDomainChars})*)?#{validDomainChars}\.)/);
	  twttr.txt.regexen.validGTLD = regexSupplant(RegExp(
	'(?:(?:' +
	    '삼성|닷컴|닷넷|香格里拉|餐厅|食品|飞利浦|電訊盈科|集团|通販|购物|谷歌|诺基亚|联通|网络|网站|网店|网址|组织机构|移动|珠宝|点看|游戏|淡马锡|机构|書籍|时尚|新闻|政府|' +
	    '政务|手表|手机|我爱你|慈善|微博|广东|工行|家電|娱乐|天主教|大拿|大众汽车|在线|嘉里大酒店|嘉里|商标|商店|商城|公益|公司|八卦|健康|信息|佛山|企业|中文网|中信|世界|' +
	    'ポイント|ファッション|セール|ストア|コム|グーグル|クラウド|みんな|คอม|संगठन|नेट|कॉम|همراه|موقع|موبايلي|كوم|كاثوليك|عرب|شبكة|' +
	    'بيتك|بازار|العليان|ارامكو|اتصالات|ابوظبي|קום|сайт|рус|орг|онлайн|москва|ком|католик|дети|' +
	    'zuerich|zone|zippo|zip|zero|zara|zappos|yun|youtube|you|yokohama|yoga|yodobashi|yandex|yamaxun|' +
	    'yahoo|yachts|xyz|xxx|xperia|xin|xihuan|xfinity|xerox|xbox|wtf|wtc|wow|world|works|work|woodside|' +
	    'wolterskluwer|wme|winners|wine|windows|win|williamhill|wiki|wien|whoswho|weir|weibo|wedding|wed|' +
	    'website|weber|webcam|weatherchannel|weather|watches|watch|warman|wanggou|wang|walter|walmart|' +
	    'wales|vuelos|voyage|voto|voting|vote|volvo|volkswagen|vodka|vlaanderen|vivo|viva|vistaprint|' +
	    'vista|vision|visa|virgin|vip|vin|villas|viking|vig|video|viajes|vet|versicherung|' +
	    'vermögensberatung|vermögensberater|verisign|ventures|vegas|vanguard|vana|vacations|ups|uol|uno|' +
	    'university|unicom|uconnect|ubs|ubank|tvs|tushu|tunes|tui|tube|trv|trust|travelersinsurance|' +
	    'travelers|travelchannel|travel|training|trading|trade|toys|toyota|town|tours|total|toshiba|' +
	    'toray|top|tools|tokyo|today|tmall|tkmaxx|tjx|tjmaxx|tirol|tires|tips|tiffany|tienda|tickets|' +
	    'tiaa|theatre|theater|thd|teva|tennis|temasek|telefonica|telecity|tel|technology|tech|team|tdk|' +
	    'tci|taxi|tax|tattoo|tatar|tatamotors|target|taobao|talk|taipei|tab|systems|symantec|sydney|' +
	    'swiss|swiftcover|swatch|suzuki|surgery|surf|support|supply|supplies|sucks|style|study|studio|' +
	    'stream|store|storage|stockholm|stcgroup|stc|statoil|statefarm|statebank|starhub|star|staples|' +
	    'stada|srt|srl|spreadbetting|spot|spiegel|space|soy|sony|song|solutions|solar|sohu|software|' +
	    'softbank|social|soccer|sncf|smile|smart|sling|skype|sky|skin|ski|site|singles|sina|silk|shriram|' +
	    'showtime|show|shouji|shopping|shop|shoes|shiksha|shia|shell|shaw|sharp|shangrila|sfr|sexy|sex|' +
	    'sew|seven|ses|services|sener|select|seek|security|secure|seat|search|scot|scor|scjohnson|' +
	    'science|schwarz|schule|school|scholarships|schmidt|schaeffler|scb|sca|sbs|sbi|saxo|save|sas|' +
	    'sarl|sapo|sap|sanofi|sandvikcoromant|sandvik|samsung|samsclub|salon|sale|sakura|safety|safe|' +
	    'saarland|ryukyu|rwe|run|ruhr|rugby|rsvp|room|rogers|rodeo|rocks|rocher|rmit|rip|rio|ril|' +
	    'rightathome|ricoh|richardli|rich|rexroth|reviews|review|restaurant|rest|republican|report|' +
	    'repair|rentals|rent|ren|reliance|reit|reisen|reise|rehab|redumbrella|redstone|red|recipes|' +
	    'realty|realtor|realestate|read|raid|radio|racing|qvc|quest|quebec|qpon|pwc|pub|prudential|pru|' +
	    'protection|property|properties|promo|progressive|prof|productions|prod|pro|prime|press|praxi|' +
	    'pramerica|post|porn|politie|poker|pohl|pnc|plus|plumbing|playstation|play|place|pizza|pioneer|' +
	    'pink|ping|pin|pid|pictures|pictet|pics|piaget|physio|photos|photography|photo|phone|philips|phd|' +
	    'pharmacy|pfizer|pet|pccw|pay|passagens|party|parts|partners|pars|paris|panerai|panasonic|' +
	    'pamperedchef|page|ovh|ott|otsuka|osaka|origins|orientexpress|organic|org|orange|oracle|open|ooo|' +
	    'onyourside|online|onl|ong|one|omega|ollo|oldnavy|olayangroup|olayan|okinawa|office|off|observer|' +
	    'obi|nyc|ntt|nrw|nra|nowtv|nowruz|now|norton|northwesternmutual|nokia|nissay|nissan|ninja|nikon|' +
	    'nike|nico|nhk|ngo|nfl|nexus|nextdirect|next|news|newholland|new|neustar|network|netflix|netbank|' +
	    'net|nec|nba|navy|natura|nationwide|name|nagoya|nadex|nab|mutuelle|mutual|museum|mtr|mtpc|mtn|' +
	    'msd|movistar|movie|mov|motorcycles|moto|moscow|mortgage|mormon|mopar|montblanc|monster|money|' +
	    'monash|mom|moi|moe|moda|mobily|mobile|mobi|mma|mls|mlb|mitsubishi|mit|mint|mini|mil|microsoft|' +
	    'miami|metlife|merckmsd|meo|menu|men|memorial|meme|melbourne|meet|media|med|mckinsey|mcdonalds|' +
	    'mcd|mba|mattel|maserati|marshalls|marriott|markets|marketing|market|map|mango|management|man|' +
	    'makeup|maison|maif|madrid|macys|luxury|luxe|lupin|lundbeck|ltda|ltd|lplfinancial|lpl|love|lotto|' +
	    'lotte|london|lol|loft|locus|locker|loans|loan|lixil|living|live|lipsy|link|linde|lincoln|limo|' +
	    'limited|lilly|like|lighting|lifestyle|lifeinsurance|life|lidl|liaison|lgbt|lexus|lego|legal|' +
	    'lefrak|leclerc|lease|lds|lawyer|law|latrobe|latino|lat|lasalle|lanxess|landrover|land|lancome|' +
	    'lancia|lancaster|lamer|lamborghini|ladbrokes|lacaixa|kyoto|kuokgroup|kred|krd|kpn|kpmg|kosher|' +
	    'komatsu|koeln|kiwi|kitchen|kindle|kinder|kim|kia|kfh|kerryproperties|kerrylogistics|kerryhotels|' +
	    'kddi|kaufen|juniper|juegos|jprs|jpmorgan|joy|jot|joburg|jobs|jnj|jmp|jll|jlc|jio|jewelry|jetzt|' +
	    'jeep|jcp|jcb|java|jaguar|iwc|iveco|itv|itau|istanbul|ist|ismaili|iselect|irish|ipiranga|' +
	    'investments|intuit|international|intel|int|insure|insurance|institute|ink|ing|info|infiniti|' +
	    'industries|immobilien|immo|imdb|imamat|ikano|iinet|ifm|ieee|icu|ice|icbc|ibm|hyundai|hyatt|' +
	    'hughes|htc|hsbc|how|house|hotmail|hotels|hoteles|hot|hosting|host|hospital|horse|honeywell|' +
	    'honda|homesense|homes|homegoods|homedepot|holiday|holdings|hockey|hkt|hiv|hitachi|hisamitsu|' +
	    'hiphop|hgtv|hermes|here|helsinki|help|healthcare|health|hdfcbank|hdfc|hbo|haus|hangout|hamburg|' +
	    'hair|guru|guitars|guide|guge|gucci|guardian|group|grocery|gripe|green|gratis|graphics|grainger|' +
	    'gov|got|gop|google|goog|goodyear|goodhands|goo|golf|goldpoint|gold|godaddy|gmx|gmo|gmbh|gmail|' +
	    'globo|global|gle|glass|glade|giving|gives|gifts|gift|ggee|george|genting|gent|gea|gdn|gbiz|' +
	    'garden|gap|games|game|gallup|gallo|gallery|gal|fyi|futbol|furniture|fund|fun|fujixerox|fujitsu|' +
	    'ftr|frontier|frontdoor|frogans|frl|fresenius|free|fox|foundation|forum|forsale|forex|ford|' +
	    'football|foodnetwork|food|foo|fly|flsmidth|flowers|florist|flir|flights|flickr|fitness|fit|' +
	    'fishing|fish|firmdale|firestone|fire|financial|finance|final|film|fido|fidelity|fiat|ferrero|' +
	    'ferrari|feedback|fedex|fast|fashion|farmers|farm|fans|fan|family|faith|fairwinds|fail|fage|' +
	    'extraspace|express|exposed|expert|exchange|everbank|events|eus|eurovision|etisalat|esurance|' +
	    'estate|esq|erni|ericsson|equipment|epson|epost|enterprises|engineering|engineer|energy|emerck|' +
	    'email|education|edu|edeka|eco|eat|earth|dvr|dvag|durban|dupont|duns|dunlop|duck|dubai|dtv|drive|' +
	    'download|dot|doosan|domains|doha|dog|dodge|doctor|docs|dnp|diy|dish|discover|discount|directory|' +
	    'direct|digital|diet|diamonds|dhl|dev|design|desi|dentist|dental|democrat|delta|deloitte|dell|' +
	    'delivery|degree|deals|dealer|deal|dds|dclk|day|datsun|dating|date|data|dance|dad|dabur|cyou|' +
	    'cymru|cuisinella|csc|cruises|cruise|crs|crown|cricket|creditunion|creditcard|credit|courses|' +
	    'coupons|coupon|country|corsica|coop|cool|cookingchannel|cooking|contractors|contact|consulting|' +
	    'construction|condos|comsec|computer|compare|company|community|commbank|comcast|com|cologne|' +
	    'college|coffee|codes|coach|clubmed|club|cloud|clothing|clinique|clinic|click|cleaning|claims|' +
	    'cityeats|city|citic|citi|citadel|cisco|circle|cipriani|church|chrysler|chrome|christmas|chloe|' +
	    'chintai|cheap|chat|chase|channel|chanel|cfd|cfa|cern|ceo|center|ceb|cbs|cbre|cbn|cba|catholic|' +
	    'catering|cat|casino|cash|caseih|case|casa|cartier|cars|careers|career|care|cards|caravan|car|' +
	    'capitalone|capital|capetown|canon|cancerresearch|camp|camera|cam|calvinklein|call|cal|cafe|cab|' +
	    'bzh|buzz|buy|business|builders|build|bugatti|budapest|brussels|brother|broker|broadway|' +
	    'bridgestone|bradesco|box|boutique|bot|boston|bostik|bosch|boots|booking|book|boo|bond|bom|bofa|' +
	    'boehringer|boats|bnpparibas|bnl|bmw|bms|blue|bloomberg|blog|blockbuster|blanco|blackfriday|' +
	    'black|biz|bio|bingo|bing|bike|bid|bible|bharti|bet|bestbuy|best|berlin|bentley|beer|beauty|' +
	    'beats|bcn|bcg|bbva|bbt|bbc|bayern|bauhaus|basketball|baseball|bargains|barefoot|barclays|' +
	    'barclaycard|barcelona|bar|bank|band|bananarepublic|banamex|baidu|baby|azure|axa|aws|avianca|' +
	    'autos|auto|author|auspost|audio|audible|audi|auction|attorney|athleta|associates|asia|asda|arte|' +
	    'art|arpa|army|archi|aramco|arab|aquarelle|apple|app|apartments|aol|anz|anquan|android|analytics|' +
	    'amsterdam|amica|amfam|amex|americanfamily|americanexpress|alstom|alsace|ally|allstate|allfinanz|' +
	    'alipay|alibaba|alfaromeo|akdn|airtel|airforce|airbus|aigo|aig|agency|agakhan|africa|afl|' +
	    'afamilycompany|aetna|aero|aeg|adult|ads|adac|actor|active|aco|accountants|accountant|accenture|' +
	    'academy|abudhabi|abogado|able|abc|abbvie|abbott|abb|abarth|aarp|aaa|onion' +
	')(?=[^0-9a-zA-Z@]|$))'));
	  twttr.txt.regexen.validCCTLD = regexSupplant(RegExp(
	'(?:(?:' +
	    '한국|香港|澳門|新加坡|台灣|台湾|中國|中国|გე|ไทย|ලංකා|ഭാരതം|ಭಾರತ|భారత్|சிங்கப்பூர்|இலங்கை|இந்தியா|ଭାରତ|ભારત|ਭਾਰਤ|' +
	    'ভাৰত|ভারত|বাংলা|भारोत|भारतम्|भारत|ڀارت|پاکستان|مليسيا|مصر|قطر|فلسطين|عمان|عراق|سورية|سودان|تونس|' +
	    'بھارت|بارت|ایران|امارات|المغرب|السعودية|الجزائر|الاردن|հայ|қаз|укр|срб|рф|мон|мкд|ею|бел|бг|ελ|' +
	    'zw|zm|za|yt|ye|ws|wf|vu|vn|vi|vg|ve|vc|va|uz|uy|us|um|uk|ug|ua|tz|tw|tv|tt|tr|tp|to|tn|tm|tl|tk|' +
	    'tj|th|tg|tf|td|tc|sz|sy|sx|sv|su|st|ss|sr|so|sn|sm|sl|sk|sj|si|sh|sg|se|sd|sc|sb|sa|rw|ru|rs|ro|' +
	    're|qa|py|pw|pt|ps|pr|pn|pm|pl|pk|ph|pg|pf|pe|pa|om|nz|nu|nr|np|no|nl|ni|ng|nf|ne|nc|na|mz|my|mx|' +
	    'mw|mv|mu|mt|ms|mr|mq|mp|mo|mn|mm|ml|mk|mh|mg|mf|me|md|mc|ma|ly|lv|lu|lt|ls|lr|lk|li|lc|lb|la|kz|' +
	    'ky|kw|kr|kp|kn|km|ki|kh|kg|ke|jp|jo|jm|je|it|is|ir|iq|io|in|im|il|ie|id|hu|ht|hr|hn|hm|hk|gy|gw|' +
	    'gu|gt|gs|gr|gq|gp|gn|gm|gl|gi|gh|gg|gf|ge|gd|gb|ga|fr|fo|fm|fk|fj|fi|eu|et|es|er|eh|eg|ee|ec|dz|' +
	    'do|dm|dk|dj|de|cz|cy|cx|cw|cv|cu|cr|co|cn|cm|cl|ck|ci|ch|cg|cf|cd|cc|ca|bz|by|bw|bv|bt|bs|br|bq|' +
	    'bo|bn|bm|bl|bj|bi|bh|bg|bf|be|bd|bb|ba|az|ax|aw|au|at|as|ar|aq|ao|an|am|al|ai|ag|af|ae|ad|ac' +
	')(?=[^0-9a-zA-Z@]|$))'));
	  twttr.txt.regexen.validPunycode = /(?:xn--[0-9a-z]+)/;
	  twttr.txt.regexen.validSpecialCCTLD = /(?:(?:co|tv)(?=[^0-9a-zA-Z@]|$))/;
	  twttr.txt.regexen.validDomain = regexSupplant(/(?:#{validSubdomain}*#{validDomainName}(?:#{validGTLD}|#{validCCTLD}|#{validPunycode}))/);
	  twttr.txt.regexen.validAsciiDomain = regexSupplant(/(?:(?:[\-a-z0-9#{latinAccentChars}]+)\.)+(?:#{validGTLD}|#{validCCTLD}|#{validPunycode})/gi);
	  twttr.txt.regexen.invalidShortDomain = regexSupplant(/^#{validDomainName}#{validCCTLD}$/i);
	  twttr.txt.regexen.validSpecialShortDomain = regexSupplant(/^#{validDomainName}#{validSpecialCCTLD}$/i);
	  twttr.txt.regexen.validPortNumber = /[0-9]+/;
	  twttr.txt.regexen.cyrillicLettersAndMarks = /\u0400-\u04FF/;
	  twttr.txt.regexen.validGeneralUrlPathChars = regexSupplant(/[a-z#{cyrillicLettersAndMarks}0-9!\*';:=\+,\.\$\/%#\[\]\-_~@\|&#{latinAccentChars}]/i);
	  // Allow URL paths to contain up to two nested levels of balanced parens
	  //  1. Used in Wikipedia URLs like /Primer_(film)
	  //  2. Used in IIS sessions like /S(dfd346)/
	  //  3. Used in Rdio URLs like /track/We_Up_(Album_Version_(Edited))/
	  twttr.txt.regexen.validUrlBalancedParens = regexSupplant(
	    '\\('                                   +
	      '(?:'                                 +
	        '#{validGeneralUrlPathChars}+'      +
	        '|'                                 +
	        // allow one nested level of balanced parentheses
	        '(?:'                               +
	          '#{validGeneralUrlPathChars}*'    +
	          '\\('                             +
	            '#{validGeneralUrlPathChars}+'  +
	          '\\)'                             +
	          '#{validGeneralUrlPathChars}*'    +
	        ')'                                 +
	      ')'                                   +
	    '\\)'
	  , 'i');
	  // Valid end-of-path chracters (so /foo. does not gobble the period).
	  // 1. Allow =&# for empty URL parameters and other URL-join artifacts
	  twttr.txt.regexen.validUrlPathEndingChars = regexSupplant(/[\+\-a-z#{cyrillicLettersAndMarks}0-9=_#\/#{latinAccentChars}]|(?:#{validUrlBalancedParens})/i);
	  // Allow @ in a url, but only in the middle. Catch things like http://example.com/@user/
	  twttr.txt.regexen.validUrlPath = regexSupplant('(?:' +
	    '(?:' +
	      '#{validGeneralUrlPathChars}*' +
	        '(?:#{validUrlBalancedParens}#{validGeneralUrlPathChars}*)*' +
	        '#{validUrlPathEndingChars}'+
	      ')|(?:@#{validGeneralUrlPathChars}+\/)'+
	    ')', 'i');

	  twttr.txt.regexen.validUrlQueryChars = /[a-z0-9!?\*'@\(\);:&=\+\$\/%#\[\]\-_\.,~|]/i;
	  twttr.txt.regexen.validUrlQueryEndingChars = /[a-z0-9_&=#\/]/i;
	  twttr.txt.regexen.extractUrl = regexSupplant(
	    '('                                                            + // $1 total match
	      '(#{validUrlPrecedingChars})'                                + // $2 Preceeding chracter
	      '('                                                          + // $3 URL
	        '(https?:\\/\\/)?'                                         + // $4 Protocol (optional)
	        '(#{validDomain})'                                         + // $5 Domain(s)
	        '(?::(#{validPortNumber}))?'                               + // $6 Port number (optional)
	        '(\\/#{validUrlPath}*)?'                                   + // $7 URL Path
	        '(\\?#{validUrlQueryChars}*#{validUrlQueryEndingChars})?'  + // $8 Query String
	      ')'                                                          +
	    ')'
	  , 'gi');

	  twttr.txt.regexen.validTcoUrl = /^https?:\/\/t\.co\/[a-z0-9]+/i;
	  twttr.txt.regexen.urlHasProtocol = /^https?:\/\//i;
	  twttr.txt.regexen.urlHasHttps = /^https:\/\//i;

	  // cashtag related regex
	  twttr.txt.regexen.cashtag = /[a-z]{1,6}(?:[._][a-z]{1,2})?/i;
	  twttr.txt.regexen.validCashtag = regexSupplant('(^|#{spaces})(\\$)(#{cashtag})(?=$|\\s|[#{punct}])', 'gi');

	  // These URL validation pattern strings are based on the ABNF from RFC 3986
	  twttr.txt.regexen.validateUrlUnreserved = /[a-z\u0400-\u04FF0-9\-._~]/i;
	  twttr.txt.regexen.validateUrlPctEncoded = /(?:%[0-9a-f]{2})/i;
	  twttr.txt.regexen.validateUrlSubDelims = /[!$&'()*+,;=]/i;
	  twttr.txt.regexen.validateUrlPchar = regexSupplant('(?:' +
	    '#{validateUrlUnreserved}|' +
	    '#{validateUrlPctEncoded}|' +
	    '#{validateUrlSubDelims}|' +
	    '[:|@]' +
	  ')', 'i');

	  twttr.txt.regexen.validateUrlScheme = /(?:[a-z][a-z0-9+\-.]*)/i;
	  twttr.txt.regexen.validateUrlUserinfo = regexSupplant('(?:' +
	    '#{validateUrlUnreserved}|' +
	    '#{validateUrlPctEncoded}|' +
	    '#{validateUrlSubDelims}|' +
	    ':' +
	  ')*', 'i');

	  twttr.txt.regexen.validateUrlDecOctet = /(?:[0-9]|(?:[1-9][0-9])|(?:1[0-9]{2})|(?:2[0-4][0-9])|(?:25[0-5]))/i;
	  twttr.txt.regexen.validateUrlIpv4 = regexSupplant(/(?:#{validateUrlDecOctet}(?:\.#{validateUrlDecOctet}){3})/i);

	  // Punting on real IPv6 validation for now
	  twttr.txt.regexen.validateUrlIpv6 = /(?:\[[a-f0-9:\.]+\])/i;

	  // Also punting on IPvFuture for now
	  twttr.txt.regexen.validateUrlIp = regexSupplant('(?:' +
	    '#{validateUrlIpv4}|' +
	    '#{validateUrlIpv6}' +
	  ')', 'i');

	  // This is more strict than the rfc specifies
	  twttr.txt.regexen.validateUrlSubDomainSegment = /(?:[a-z0-9](?:[a-z0-9_\-]*[a-z0-9])?)/i;
	  twttr.txt.regexen.validateUrlDomainSegment = /(?:[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)/i;
	  twttr.txt.regexen.validateUrlDomainTld = /(?:[a-z](?:[a-z0-9\-]*[a-z0-9])?)/i;
	  twttr.txt.regexen.validateUrlDomain = regexSupplant(/(?:(?:#{validateUrlSubDomainSegment}\.)*(?:#{validateUrlDomainSegment}\.)#{validateUrlDomainTld})/i);

	  twttr.txt.regexen.validateUrlHost = regexSupplant('(?:' +
	    '#{validateUrlIp}|' +
	    '#{validateUrlDomain}' +
	  ')', 'i');

	  // Unencoded internationalized domains - this doesn't check for invalid UTF-8 sequences
	  twttr.txt.regexen.validateUrlUnicodeSubDomainSegment = /(?:(?:[a-z0-9]|[^\u0000-\u007f])(?:(?:[a-z0-9_\-]|[^\u0000-\u007f])*(?:[a-z0-9]|[^\u0000-\u007f]))?)/i;
	  twttr.txt.regexen.validateUrlUnicodeDomainSegment = /(?:(?:[a-z0-9]|[^\u0000-\u007f])(?:(?:[a-z0-9\-]|[^\u0000-\u007f])*(?:[a-z0-9]|[^\u0000-\u007f]))?)/i;
	  twttr.txt.regexen.validateUrlUnicodeDomainTld = /(?:(?:[a-z]|[^\u0000-\u007f])(?:(?:[a-z0-9\-]|[^\u0000-\u007f])*(?:[a-z0-9]|[^\u0000-\u007f]))?)/i;
	  twttr.txt.regexen.validateUrlUnicodeDomain = regexSupplant(/(?:(?:#{validateUrlUnicodeSubDomainSegment}\.)*(?:#{validateUrlUnicodeDomainSegment}\.)#{validateUrlUnicodeDomainTld})/i);

	  twttr.txt.regexen.validateUrlUnicodeHost = regexSupplant('(?:' +
	    '#{validateUrlIp}|' +
	    '#{validateUrlUnicodeDomain}' +
	  ')', 'i');

	  twttr.txt.regexen.validateUrlPort = /[0-9]{1,5}/;

	  twttr.txt.regexen.validateUrlUnicodeAuthority = regexSupplant(
	    '(?:(#{validateUrlUserinfo})@)?'  + // $1 userinfo
	    '(#{validateUrlUnicodeHost})'     + // $2 host
	    '(?::(#{validateUrlPort}))?'        //$3 port
	  , "i");

	  twttr.txt.regexen.validateUrlAuthority = regexSupplant(
	    '(?:(#{validateUrlUserinfo})@)?' + // $1 userinfo
	    '(#{validateUrlHost})'           + // $2 host
	    '(?::(#{validateUrlPort}))?'       // $3 port
	  , "i");

	  twttr.txt.regexen.validateUrlPath = regexSupplant(/(\/#{validateUrlPchar}*)*/i);
	  twttr.txt.regexen.validateUrlQuery = regexSupplant(/(#{validateUrlPchar}|\/|\?)*/i);
	  twttr.txt.regexen.validateUrlFragment = regexSupplant(/(#{validateUrlPchar}|\/|\?)*/i);

	  // Modified version of RFC 3986 Appendix B
	  twttr.txt.regexen.validateUrlUnencoded = regexSupplant(
	    '^'                               + // Full URL
	    '(?:'                             +
	      '([^:/?#]+):\\/\\/'             + // $1 Scheme
	    ')?'                              +
	    '([^/?#]*)'                       + // $2 Authority
	    '([^?#]*)'                        + // $3 Path
	    '(?:'                             +
	      '\\?([^#]*)'                    + // $4 Query
	    ')?'                              +
	    '(?:'                             +
	      '#(.*)'                         + // $5 Fragment
	    ')?$'
	  , "i");


	  // Default CSS class for auto-linked lists (along with the url class)
	  var DEFAULT_LIST_CLASS = "tweet-url list-slug";
	  // Default CSS class for auto-linked usernames (along with the url class)
	  var DEFAULT_USERNAME_CLASS = "tweet-url username";
	  // Default CSS class for auto-linked hashtags (along with the url class)
	  var DEFAULT_HASHTAG_CLASS = "tweet-url hashtag";
	  // Default CSS class for auto-linked cashtags (along with the url class)
	  var DEFAULT_CASHTAG_CLASS = "tweet-url cashtag";
	  // Options which should not be passed as HTML attributes
	  var OPTIONS_NOT_ATTRIBUTES = {'urlClass':true, 'listClass':true, 'usernameClass':true, 'hashtagClass':true, 'cashtagClass':true,
	                            'usernameUrlBase':true, 'listUrlBase':true, 'hashtagUrlBase':true, 'cashtagUrlBase':true,
	                            'usernameUrlBlock':true, 'listUrlBlock':true, 'hashtagUrlBlock':true, 'linkUrlBlock':true,
	                            'usernameIncludeSymbol':true, 'suppressLists':true, 'suppressNoFollow':true, 'targetBlank':true,
	                            'suppressDataScreenName':true, 'urlEntities':true, 'symbolTag':true, 'textWithSymbolTag':true, 'urlTarget':true,
	                            'invisibleTagAttrs':true, 'linkAttributeBlock':true, 'linkTextBlock': true, 'htmlEscapeNonEntities': true
	                            };

	  var BOOLEAN_ATTRIBUTES = {'disabled':true, 'readonly':true, 'multiple':true, 'checked':true};

	  // Simple object cloning function for simple objects
	  function clone(o) {
	    var r = {};
	    for (var k in o) {
	      if (o.hasOwnProperty(k)) {
	        r[k] = o[k];
	      }
	    }

	    return r;
	  }

	  twttr.txt.tagAttrs = function(attributes) {
	    var htmlAttrs = "";
	    for (var k in attributes) {
	      var v = attributes[k];
	      if (BOOLEAN_ATTRIBUTES[k]) {
	        v = v ? k : null;
	      }
	      if (v == null) continue;
	      htmlAttrs += " " + twttr.txt.htmlEscape(k) + "=\"" + twttr.txt.htmlEscape(v.toString()) + "\"";
	    }
	    return htmlAttrs;
	  };

	  twttr.txt.linkToText = function(entity, text, attributes, options) {
	    if (!options.suppressNoFollow) {
	      attributes.rel = "nofollow";
	    }
	    // if linkAttributeBlock is specified, call it to modify the attributes
	    if (options.linkAttributeBlock) {
	      options.linkAttributeBlock(entity, attributes);
	    }
	    // if linkTextBlock is specified, call it to get a new/modified link text
	    if (options.linkTextBlock) {
	      text = options.linkTextBlock(entity, text);
	    }
	    var d = {
	      text: text,
	      attr: twttr.txt.tagAttrs(attributes)
	    };
	    return stringSupplant("<a#{attr}>#{text}</a>", d);
	  };

	  twttr.txt.linkToTextWithSymbol = function(entity, symbol, text, attributes, options) {
	    var taggedSymbol = options.symbolTag ? "<" + options.symbolTag + ">" + symbol + "</"+ options.symbolTag + ">" : symbol;
	    text = twttr.txt.htmlEscape(text);
	    var taggedText = options.textWithSymbolTag ? "<" + options.textWithSymbolTag + ">" + text + "</"+ options.textWithSymbolTag + ">" : text;

	    if (options.usernameIncludeSymbol || !symbol.match(twttr.txt.regexen.atSigns)) {
	      return twttr.txt.linkToText(entity, taggedSymbol + taggedText, attributes, options);
	    } else {
	      return taggedSymbol + twttr.txt.linkToText(entity, taggedText, attributes, options);
	    }
	  };

	  twttr.txt.linkToHashtag = function(entity, text, options) {
	    var hash = text.substring(entity.indices[0], entity.indices[0] + 1);
	    var hashtag = twttr.txt.htmlEscape(entity.hashtag);
	    var attrs = clone(options.htmlAttrs || {});
	    attrs.href = options.hashtagUrlBase + hashtag;
	    attrs.title = "#" + hashtag;
	    attrs["class"] = options.hashtagClass;
	    if (hashtag.charAt(0).match(twttr.txt.regexen.rtl_chars)){
	      attrs["class"] += " rtl";
	    }
	    if (options.targetBlank) {
	      attrs.target = '_blank';
	    }

	    return twttr.txt.linkToTextWithSymbol(entity, hash, hashtag, attrs, options);
	  };

	  twttr.txt.linkToCashtag = function(entity, text, options) {
	    var cashtag = twttr.txt.htmlEscape(entity.cashtag);
	    var attrs = clone(options.htmlAttrs || {});
	    attrs.href = options.cashtagUrlBase + cashtag;
	    attrs.title = "$" + cashtag;
	    attrs["class"] =  options.cashtagClass;
	    if (options.targetBlank) {
	      attrs.target = '_blank';
	    }

	    return twttr.txt.linkToTextWithSymbol(entity, "$", cashtag, attrs, options);
	  };

	  twttr.txt.linkToMentionAndList = function(entity, text, options) {
	    var at = text.substring(entity.indices[0], entity.indices[0] + 1);
	    var user = twttr.txt.htmlEscape(entity.screenName);
	    var slashListname = twttr.txt.htmlEscape(entity.listSlug);
	    var isList = entity.listSlug && !options.suppressLists;
	    var attrs = clone(options.htmlAttrs || {});
	    attrs["class"] = (isList ? options.listClass : options.usernameClass);
	    attrs.href = isList ? options.listUrlBase + user + slashListname : options.usernameUrlBase + user;
	    if (!isList && !options.suppressDataScreenName) {
	      attrs['data-screen-name'] = user;
	    }
	    if (options.targetBlank) {
	      attrs.target = '_blank';
	    }

	    return twttr.txt.linkToTextWithSymbol(entity, at, isList ? user + slashListname : user, attrs, options);
	  };

	  twttr.txt.linkToUrl = function(entity, text, options) {
	    var url = entity.url;
	    var displayUrl = url;
	    var linkText = twttr.txt.htmlEscape(displayUrl);

	    // If the caller passed a urlEntities object (provided by a Twitter API
	    // response with include_entities=true), we use that to render the display_url
	    // for each URL instead of it's underlying t.co URL.
	    var urlEntity = (options.urlEntities && options.urlEntities[url]) || entity;
	    if (urlEntity.display_url) {
	      linkText = twttr.txt.linkTextWithEntity(urlEntity, options);
	    }

	    var attrs = clone(options.htmlAttrs || {});

	    if (!url.match(twttr.txt.regexen.urlHasProtocol)) {
	      url = "http://" + url;
	    }
	    attrs.href = url;

	    if (options.targetBlank) {
	      attrs.target = '_blank';
	    }

	    // set class only if urlClass is specified.
	    if (options.urlClass) {
	      attrs["class"] = options.urlClass;
	    }

	    // set target only if urlTarget is specified.
	    if (options.urlTarget) {
	      attrs.target = options.urlTarget;
	    }

	    if (!options.title && urlEntity.display_url) {
	      attrs.title = urlEntity.expanded_url;
	    }

	    return twttr.txt.linkToText(entity, linkText, attrs, options);
	  };

	  twttr.txt.linkTextWithEntity = function (entity, options) {
	    var displayUrl = entity.display_url;
	    var expandedUrl = entity.expanded_url;

	    // Goal: If a user copies and pastes a tweet containing t.co'ed link, the resulting paste
	    // should contain the full original URL (expanded_url), not the display URL.
	    //
	    // Method: Whenever possible, we actually emit HTML that contains expanded_url, and use
	    // font-size:0 to hide those parts that should not be displayed (because they are not part of display_url).
	    // Elements with font-size:0 get copied even though they are not visible.
	    // Note that display:none doesn't work here. Elements with display:none don't get copied.
	    //
	    // Additionally, we want to *display* ellipses, but we don't want them copied.  To make this happen we
	    // wrap the ellipses in a tco-ellipsis class and provide an onCopy handler that sets display:none on
	    // everything with the tco-ellipsis class.
	    //
	    // Exception: pic.twitter.com images, for which expandedUrl = "https://twitter.com/#!/username/status/1234/photo/1
	    // For those URLs, display_url is not a substring of expanded_url, so we don't do anything special to render the elided parts.
	    // For a pic.twitter.com URL, the only elided part will be the "https://", so this is fine.

	    var displayUrlSansEllipses = displayUrl.replace(/…/g, ""); // We have to disregard ellipses for matching
	    // Note: we currently only support eliding parts of the URL at the beginning or the end.
	    // Eventually we may want to elide parts of the URL in the *middle*.  If so, this code will
	    // become more complicated.  We will probably want to create a regexp out of display URL,
	    // replacing every ellipsis with a ".*".
	    if (expandedUrl.indexOf(displayUrlSansEllipses) != -1) {
	      var displayUrlIndex = expandedUrl.indexOf(displayUrlSansEllipses);
	      var v = {
	        displayUrlSansEllipses: displayUrlSansEllipses,
	        // Portion of expandedUrl that precedes the displayUrl substring
	        beforeDisplayUrl: expandedUrl.substr(0, displayUrlIndex),
	        // Portion of expandedUrl that comes after displayUrl
	        afterDisplayUrl: expandedUrl.substr(displayUrlIndex + displayUrlSansEllipses.length),
	        precedingEllipsis: displayUrl.match(/^…/) ? "…" : "",
	        followingEllipsis: displayUrl.match(/…$/) ? "…" : ""
	      };
	      for (var k in v) {
	        if (v.hasOwnProperty(k)) {
	          v[k] = twttr.txt.htmlEscape(v[k]);
	        }
	      }
	      // As an example: The user tweets "hi http://longdomainname.com/foo"
	      // This gets shortened to "hi http://t.co/xyzabc", with display_url = "…nname.com/foo"
	      // This will get rendered as:
	      // <span class='tco-ellipsis'> <!-- This stuff should get displayed but not copied -->
	      //   …
	      //   <!-- There's a chance the onCopy event handler might not fire. In case that happens,
	      //        we include an &nbsp; here so that the … doesn't bump up against the URL and ruin it.
	      //        The &nbsp; is inside the tco-ellipsis span so that when the onCopy handler *does*
	      //        fire, it doesn't get copied.  Otherwise the copied text would have two spaces in a row,
	      //        e.g. "hi  http://longdomainname.com/foo".
	      //   <span style='font-size:0'>&nbsp;</span>
	      // </span>
	      // <span style='font-size:0'>  <!-- This stuff should get copied but not displayed -->
	      //   http://longdomai
	      // </span>
	      // <span class='js-display-url'> <!-- This stuff should get displayed *and* copied -->
	      //   nname.com/foo
	      // </span>
	      // <span class='tco-ellipsis'> <!-- This stuff should get displayed but not copied -->
	      //   <span style='font-size:0'>&nbsp;</span>
	      //   …
	      // </span>
	      v['invisible'] = options.invisibleTagAttrs;
	      return stringSupplant("<span class='tco-ellipsis'>#{precedingEllipsis}<span #{invisible}>&nbsp;</span></span><span #{invisible}>#{beforeDisplayUrl}</span><span class='js-display-url'>#{displayUrlSansEllipses}</span><span #{invisible}>#{afterDisplayUrl}</span><span class='tco-ellipsis'><span #{invisible}>&nbsp;</span>#{followingEllipsis}</span>", v);
	    }
	    return displayUrl;
	  };

	  twttr.txt.autoLinkEntities = function(text, entities, options) {
	    options = clone(options || {});

	    options.hashtagClass = options.hashtagClass || DEFAULT_HASHTAG_CLASS;
	    options.hashtagUrlBase = options.hashtagUrlBase || "https://twitter.com/#!/search?q=%23";
	    options.cashtagClass = options.cashtagClass || DEFAULT_CASHTAG_CLASS;
	    options.cashtagUrlBase = options.cashtagUrlBase || "https://twitter.com/#!/search?q=%24";
	    options.listClass = options.listClass || DEFAULT_LIST_CLASS;
	    options.usernameClass = options.usernameClass || DEFAULT_USERNAME_CLASS;
	    options.usernameUrlBase = options.usernameUrlBase || "https://twitter.com/";
	    options.listUrlBase = options.listUrlBase || "https://twitter.com/";
	    options.htmlAttrs = twttr.txt.extractHtmlAttrsFromOptions(options);
	    options.invisibleTagAttrs = options.invisibleTagAttrs || "style='position:absolute;left:-9999px;'";

	    // remap url entities to hash
	    var urlEntities, i, len;
	    if(options.urlEntities) {
	      urlEntities = {};
	      for(i = 0, len = options.urlEntities.length; i < len; i++) {
	        urlEntities[options.urlEntities[i].url] = options.urlEntities[i];
	      }
	      options.urlEntities = urlEntities;
	    }

	    var result = "";
	    var beginIndex = 0;

	    // sort entities by start index
	    entities.sort(function(a,b){ return a.indices[0] - b.indices[0]; });

	    var nonEntity = options.htmlEscapeNonEntities ? twttr.txt.htmlEscape : function(text) {
	      return text;
	    };

	    for (var i = 0; i < entities.length; i++) {
	      var entity = entities[i];
	      result += nonEntity(text.substring(beginIndex, entity.indices[0]));

	      if (entity.url) {
	        result += twttr.txt.linkToUrl(entity, text, options);
	      } else if (entity.hashtag) {
	        result += twttr.txt.linkToHashtag(entity, text, options);
	      } else if (entity.screenName) {
	        result += twttr.txt.linkToMentionAndList(entity, text, options);
	      } else if (entity.cashtag) {
	        result += twttr.txt.linkToCashtag(entity, text, options);
	      }
	      beginIndex = entity.indices[1];
	    }
	    result += nonEntity(text.substring(beginIndex, text.length));
	    return result;
	  };

	  twttr.txt.autoLinkWithJSON = function(text, json, options) {
	    // map JSON entity to twitter-text entity
	    if (json.user_mentions) {
	      for (var i = 0; i < json.user_mentions.length; i++) {
	        // this is a @mention
	        json.user_mentions[i].screenName = json.user_mentions[i].screen_name;
	      }
	    }

	    if (json.hashtags) {
	      for (var i = 0; i < json.hashtags.length; i++) {
	        // this is a #hashtag
	        json.hashtags[i].hashtag = json.hashtags[i].text;
	      }
	    }

	    if (json.symbols) {
	      for (var i = 0; i < json.symbols.length; i++) {
	        // this is a $CASH tag
	        json.symbols[i].cashtag = json.symbols[i].text;
	      }
	    }

	    // concatenate all entities
	    var entities = [];
	    for (var key in json) {
	      entities = entities.concat(json[key]);
	    }

	    // modify indices to UTF-16
	    twttr.txt.modifyIndicesFromUnicodeToUTF16(text, entities);

	    return twttr.txt.autoLinkEntities(text, entities, options);
	  };

	  twttr.txt.extractHtmlAttrsFromOptions = function(options) {
	    var htmlAttrs = {};
	    for (var k in options) {
	      var v = options[k];
	      if (OPTIONS_NOT_ATTRIBUTES[k]) continue;
	      if (BOOLEAN_ATTRIBUTES[k]) {
	        v = v ? k : null;
	      }
	      if (v == null) continue;
	      htmlAttrs[k] = v;
	    }
	    return htmlAttrs;
	  };

	  twttr.txt.autoLink = function(text, options) {
	    var entities = twttr.txt.extractEntitiesWithIndices(text, {extractUrlsWithoutProtocol: false});
	    return twttr.txt.autoLinkEntities(text, entities, options);
	  };

	  twttr.txt.autoLinkUsernamesOrLists = function(text, options) {
	    var entities = twttr.txt.extractMentionsOrListsWithIndices(text);
	    return twttr.txt.autoLinkEntities(text, entities, options);
	  };

	  twttr.txt.autoLinkHashtags = function(text, options) {
	    var entities = twttr.txt.extractHashtagsWithIndices(text);
	    return twttr.txt.autoLinkEntities(text, entities, options);
	  };

	  twttr.txt.autoLinkCashtags = function(text, options) {
	    var entities = twttr.txt.extractCashtagsWithIndices(text);
	    return twttr.txt.autoLinkEntities(text, entities, options);
	  };

	  twttr.txt.autoLinkUrlsCustom = function(text, options) {
	    var entities = twttr.txt.extractUrlsWithIndices(text, {extractUrlsWithoutProtocol: false});
	    return twttr.txt.autoLinkEntities(text, entities, options);
	  };

	  twttr.txt.removeOverlappingEntities = function(entities) {
	    entities.sort(function(a,b){ return a.indices[0] - b.indices[0]; });

	    var prev = entities[0];
	    for (var i = 1; i < entities.length; i++) {
	      if (prev.indices[1] > entities[i].indices[0]) {
	        entities.splice(i, 1);
	        i--;
	      } else {
	        prev = entities[i];
	      }
	    }
	  };

	  twttr.txt.extractEntitiesWithIndices = function(text, options) {
	    var entities = twttr.txt.extractUrlsWithIndices(text, options)
	                    .concat(twttr.txt.extractMentionsOrListsWithIndices(text))
	                    .concat(twttr.txt.extractHashtagsWithIndices(text, {checkUrlOverlap: false}))
	                    .concat(twttr.txt.extractCashtagsWithIndices(text));

	    if (entities.length == 0) {
	      return [];
	    }

	    twttr.txt.removeOverlappingEntities(entities);
	    return entities;
	  };

	  twttr.txt.extractMentions = function(text) {
	    var screenNamesOnly = [],
	        screenNamesWithIndices = twttr.txt.extractMentionsWithIndices(text);

	    for (var i = 0; i < screenNamesWithIndices.length; i++) {
	      var screenName = screenNamesWithIndices[i].screenName;
	      screenNamesOnly.push(screenName);
	    }

	    return screenNamesOnly;
	  };

	  twttr.txt.extractMentionsWithIndices = function(text) {
	    var mentions = [],
	        mentionOrList,
	        mentionsOrLists = twttr.txt.extractMentionsOrListsWithIndices(text);

	    for (var i = 0 ; i < mentionsOrLists.length; i++) {
	      mentionOrList = mentionsOrLists[i];
	      if (mentionOrList.listSlug == '') {
	        mentions.push({
	          screenName: mentionOrList.screenName,
	          indices: mentionOrList.indices
	        });
	      }
	    }

	    return mentions;
	  };

	  /**
	   * Extract list or user mentions.
	   * (Presence of listSlug indicates a list)
	   */
	  twttr.txt.extractMentionsOrListsWithIndices = function(text) {
	    if (!text || !text.match(twttr.txt.regexen.atSigns)) {
	      return [];
	    }

	    var possibleNames = [],
	        slashListname;

	    text.replace(twttr.txt.regexen.validMentionOrList, function(match, before, atSign, screenName, slashListname, offset, chunk) {
	      var after = chunk.slice(offset + match.length);
	      if (!after.match(twttr.txt.regexen.endMentionMatch)) {
	        slashListname = slashListname || '';
	        var startPosition = offset + before.length;
	        var endPosition = startPosition + screenName.length + slashListname.length + 1;
	        possibleNames.push({
	          screenName: screenName,
	          listSlug: slashListname,
	          indices: [startPosition, endPosition]
	        });
	      }
	    });

	    return possibleNames;
	  };


	  twttr.txt.extractReplies = function(text) {
	    if (!text) {
	      return null;
	    }

	    var possibleScreenName = text.match(twttr.txt.regexen.validReply);
	    if (!possibleScreenName ||
	        RegExp.rightContext.match(twttr.txt.regexen.endMentionMatch)) {
	      return null;
	    }

	    return possibleScreenName[1];
	  };

	  twttr.txt.extractUrls = function(text, options) {
	    var urlsOnly = [],
	        urlsWithIndices = twttr.txt.extractUrlsWithIndices(text, options);

	    for (var i = 0; i < urlsWithIndices.length; i++) {
	      urlsOnly.push(urlsWithIndices[i].url);
	    }

	    return urlsOnly;
	  };

	  twttr.txt.extractUrlsWithIndices = function(text, options) {
	    if (!options) {
	      options = {extractUrlsWithoutProtocol: true};
	    }
	    if (!text || (options.extractUrlsWithoutProtocol ? !text.match(/\./) : !text.match(/:/))) {
	      return [];
	    }

	    var urls = [];

	    while (twttr.txt.regexen.extractUrl.exec(text)) {
	      var before = RegExp.$2, url = RegExp.$3, protocol = RegExp.$4, domain = RegExp.$5, path = RegExp.$7;
	      var endPosition = twttr.txt.regexen.extractUrl.lastIndex,
	          startPosition = endPosition - url.length;

	      // if protocol is missing and domain contains non-ASCII characters,
	      // extract ASCII-only domains.
	      if (!protocol) {
	        if (!options.extractUrlsWithoutProtocol
	            || before.match(twttr.txt.regexen.invalidUrlWithoutProtocolPrecedingChars)) {
	          continue;
	        }
	        var lastUrl = null,
	            asciiEndPosition = 0;
	        domain.replace(twttr.txt.regexen.validAsciiDomain, function(asciiDomain) {
	          var asciiStartPosition = domain.indexOf(asciiDomain, asciiEndPosition);
	          asciiEndPosition = asciiStartPosition + asciiDomain.length;
	          lastUrl = {
	            url: asciiDomain,
	            indices: [startPosition + asciiStartPosition, startPosition + asciiEndPosition]
	          };
	          if (path
	              || asciiDomain.match(twttr.txt.regexen.validSpecialShortDomain)
	              || !asciiDomain.match(twttr.txt.regexen.invalidShortDomain)) {
	            urls.push(lastUrl);
	          }
	        });

	        // no ASCII-only domain found. Skip the entire URL.
	        if (lastUrl == null) {
	          continue;
	        }

	        // lastUrl only contains domain. Need to add path and query if they exist.
	        if (path) {
	          lastUrl.url = url.replace(domain, lastUrl.url);
	          lastUrl.indices[1] = endPosition;
	        }
	      } else {
	        // In the case of t.co URLs, don't allow additional path characters.
	        if (url.match(twttr.txt.regexen.validTcoUrl)) {
	          url = RegExp.lastMatch;
	          endPosition = startPosition + url.length;
	        }
	        urls.push({
	          url: url,
	          indices: [startPosition, endPosition]
	        });
	      }
	    }

	    return urls;
	  };

	  twttr.txt.extractHashtags = function(text) {
	    var hashtagsOnly = [],
	        hashtagsWithIndices = twttr.txt.extractHashtagsWithIndices(text);

	    for (var i = 0; i < hashtagsWithIndices.length; i++) {
	      hashtagsOnly.push(hashtagsWithIndices[i].hashtag);
	    }

	    return hashtagsOnly;
	  };

	  twttr.txt.extractHashtagsWithIndices = function(text, options) {
	    if (!options) {
	      options = {checkUrlOverlap: true};
	    }

	    if (!text || !text.match(twttr.txt.regexen.hashSigns)) {
	      return [];
	    }

	    var tags = [];

	    text.replace(twttr.txt.regexen.validHashtag, function(match, before, hash, hashText, offset, chunk) {
	      var after = chunk.slice(offset + match.length);
	      if (after.match(twttr.txt.regexen.endHashtagMatch))
	        return;
	      var startPosition = offset + before.length;
	      var endPosition = startPosition + hashText.length + 1;
	      tags.push({
	        hashtag: hashText,
	        indices: [startPosition, endPosition]
	      });
	    });

	    if (options.checkUrlOverlap) {
	      // also extract URL entities
	      var urls = twttr.txt.extractUrlsWithIndices(text);
	      if (urls.length > 0) {
	        var entities = tags.concat(urls);
	        // remove overlap
	        twttr.txt.removeOverlappingEntities(entities);
	        // only push back hashtags
	        tags = [];
	        for (var i = 0; i < entities.length; i++) {
	          if (entities[i].hashtag) {
	            tags.push(entities[i]);
	          }
	        }
	      }
	    }

	    return tags;
	  };

	  twttr.txt.extractCashtags = function(text) {
	    var cashtagsOnly = [],
	        cashtagsWithIndices = twttr.txt.extractCashtagsWithIndices(text);

	    for (var i = 0; i < cashtagsWithIndices.length; i++) {
	      cashtagsOnly.push(cashtagsWithIndices[i].cashtag);
	    }

	    return cashtagsOnly;
	  };

	  twttr.txt.extractCashtagsWithIndices = function(text) {
	    if (!text || text.indexOf("$") == -1) {
	      return [];
	    }

	    var tags = [];

	    text.replace(twttr.txt.regexen.validCashtag, function(match, before, dollar, cashtag, offset, chunk) {
	      var startPosition = offset + before.length;
	      var endPosition = startPosition + cashtag.length + 1;
	      tags.push({
	        cashtag: cashtag,
	        indices: [startPosition, endPosition]
	      });
	    });

	    return tags;
	  };

	  twttr.txt.modifyIndicesFromUnicodeToUTF16 = function(text, entities) {
	    twttr.txt.convertUnicodeIndices(text, entities, false);
	  };

	  twttr.txt.modifyIndicesFromUTF16ToUnicode = function(text, entities) {
	    twttr.txt.convertUnicodeIndices(text, entities, true);
	  };

	  twttr.txt.getUnicodeTextLength = function(text) {
	    return text.replace(twttr.txt.regexen.non_bmp_code_pairs, ' ').length;
	  };

	  twttr.txt.convertUnicodeIndices = function(text, entities, indicesInUTF16) {
	    if (entities.length == 0) {
	      return;
	    }

	    var charIndex = 0;
	    var codePointIndex = 0;

	    // sort entities by start index
	    entities.sort(function(a,b){ return a.indices[0] - b.indices[0]; });
	    var entityIndex = 0;
	    var entity = entities[0];

	    while (charIndex < text.length) {
	      if (entity.indices[0] == (indicesInUTF16 ? charIndex : codePointIndex)) {
	        var len = entity.indices[1] - entity.indices[0];
	        entity.indices[0] = indicesInUTF16 ? codePointIndex : charIndex;
	        entity.indices[1] = entity.indices[0] + len;

	        entityIndex++;
	        if (entityIndex == entities.length) {
	          // no more entity
	          break;
	        }
	        entity = entities[entityIndex];
	      }

	      var c = text.charCodeAt(charIndex);
	      if (0xD800 <= c && c <= 0xDBFF && charIndex < text.length - 1) {
	        // Found high surrogate char
	        c = text.charCodeAt(charIndex + 1);
	        if (0xDC00 <= c && c <= 0xDFFF) {
	          // Found surrogate pair
	          charIndex++;
	        }
	      }
	      codePointIndex++;
	      charIndex++;
	    }
	  };

	  // this essentially does text.split(/<|>/)
	  // except that won't work in IE, where empty strings are ommitted
	  // so "<>".split(/<|>/) => [] in IE, but is ["", "", ""] in all others
	  // but "<<".split("<") => ["", "", ""]
	  twttr.txt.splitTags = function(text) {
	    var firstSplits = text.split("<"),
	        secondSplits,
	        allSplits = [],
	        split;

	    for (var i = 0; i < firstSplits.length; i += 1) {
	      split = firstSplits[i];
	      if (!split) {
	        allSplits.push("");
	      } else {
	        secondSplits = split.split(">");
	        for (var j = 0; j < secondSplits.length; j += 1) {
	          allSplits.push(secondSplits[j]);
	        }
	      }
	    }

	    return allSplits;
	  };

	  twttr.txt.hitHighlight = function(text, hits, options) {
	    var defaultHighlightTag = "em";

	    hits = hits || [];
	    options = options || {};

	    if (hits.length === 0) {
	      return text;
	    }

	    var tagName = options.tag || defaultHighlightTag,
	        tags = ["<" + tagName + ">", "</" + tagName + ">"],
	        chunks = twttr.txt.splitTags(text),
	        i,
	        j,
	        result = "",
	        chunkIndex = 0,
	        chunk = chunks[0],
	        prevChunksLen = 0,
	        chunkCursor = 0,
	        startInChunk = false,
	        chunkChars = chunk,
	        flatHits = [],
	        index,
	        hit,
	        tag,
	        placed,
	        hitSpot;

	    for (i = 0; i < hits.length; i += 1) {
	      for (j = 0; j < hits[i].length; j += 1) {
	        flatHits.push(hits[i][j]);
	      }
	    }

	    for (index = 0; index < flatHits.length; index += 1) {
	      hit = flatHits[index];
	      tag = tags[index % 2];
	      placed = false;

	      while (chunk != null && hit >= prevChunksLen + chunk.length) {
	        result += chunkChars.slice(chunkCursor);
	        if (startInChunk && hit === prevChunksLen + chunkChars.length) {
	          result += tag;
	          placed = true;
	        }

	        if (chunks[chunkIndex + 1]) {
	          result += "<" + chunks[chunkIndex + 1] + ">";
	        }

	        prevChunksLen += chunkChars.length;
	        chunkCursor = 0;
	        chunkIndex += 2;
	        chunk = chunks[chunkIndex];
	        chunkChars = chunk;
	        startInChunk = false;
	      }

	      if (!placed && chunk != null) {
	        hitSpot = hit - prevChunksLen;
	        result += chunkChars.slice(chunkCursor, hitSpot) + tag;
	        chunkCursor = hitSpot;
	        if (index % 2 === 0) {
	          startInChunk = true;
	        } else {
	          startInChunk = false;
	        }
	      } else if(!placed) {
	        placed = true;
	        result += tag;
	      }
	    }

	    if (chunk != null) {
	      if (chunkCursor < chunkChars.length) {
	        result += chunkChars.slice(chunkCursor);
	      }
	      for (index = chunkIndex + 1; index < chunks.length; index += 1) {
	        result += (index % 2 === 0 ? chunks[index] : "<" + chunks[index] + ">");
	      }
	    }

	    return result;
	  };

	  var MAX_LENGTH = 140;

	  // Returns the length of Tweet text with consideration to t.co URL replacement
	  // and chars outside the basic multilingual plane that use 2 UTF16 code points
	  twttr.txt.getTweetLength = function(text, options) {
	    if (!options) {
	      options = {
	          // These come from https://api.twitter.com/1.1/help/configuration.json
	          // described by https://dev.twitter.com/rest/reference/get/help/configuration
	          short_url_length: 23,
	          short_url_length_https: 23
	      };
	    }
	    var textLength = twttr.txt.getUnicodeTextLength(text),
	        urlsWithIndices = twttr.txt.extractUrlsWithIndices(text);
	    twttr.txt.modifyIndicesFromUTF16ToUnicode(text, urlsWithIndices);

	    for (var i = 0; i < urlsWithIndices.length; i++) {
	      // Subtract the length of the original URL
	      textLength += urlsWithIndices[i].indices[0] - urlsWithIndices[i].indices[1];

	      // Add 23 characters for URL starting with https://
	      // http:// URLs still use https://t.co so they are 23 characters as well
	      if (urlsWithIndices[i].url.toLowerCase().match(twttr.txt.regexen.urlHasHttps)) {
	         textLength += options.short_url_length_https;
	      } else {
	        textLength += options.short_url_length;
	      }
	    }

	    return textLength;
	  };

	  // Check the text for any reason that it may not be valid as a Tweet. This is meant as a pre-validation
	  // before posting to api.twitter.com. There are several server-side reasons for Tweets to fail but this pre-validation
	  // will allow quicker feedback.
	  //
	  // Returns false if this text is valid. Otherwise one of the following strings will be returned:
	  //
	  //   "too_long": if the text is too long
	  //   "empty": if the text is nil or empty
	  //   "invalid_characters": if the text contains non-Unicode or any of the disallowed Unicode characters
	  twttr.txt.isInvalidTweet = function(text) {
	    if (!text) {
	      return "empty";
	    }

	    // Determine max length independent of URL length
	    if (twttr.txt.getTweetLength(text) > MAX_LENGTH) {
	      return "too_long";
	    }

	    if (twttr.txt.hasInvalidCharacters(text)) {
	      return "invalid_characters";
	    }

	    return false;
	  };

	  twttr.txt.hasInvalidCharacters = function(text) {
	    return twttr.txt.regexen.invalid_chars.test(text);
	  };

	  twttr.txt.isValidTweetText = function(text) {
	    return !twttr.txt.isInvalidTweet(text);
	  };

	  twttr.txt.isValidUsername = function(username) {
	    if (!username) {
	      return false;
	    }

	    var extracted = twttr.txt.extractMentions(username);

	    // Should extract the username minus the @ sign, hence the .slice(1)
	    return extracted.length === 1 && extracted[0] === username.slice(1);
	  };

	  var VALID_LIST_RE = regexSupplant(/^#{validMentionOrList}$/);

	  twttr.txt.isValidList = function(usernameList) {
	    var match = usernameList.match(VALID_LIST_RE);

	    // Must have matched and had nothing before or after
	    return !!(match && match[1] == "" && match[4]);
	  };

	  twttr.txt.isValidHashtag = function(hashtag) {
	    if (!hashtag) {
	      return false;
	    }

	    var extracted = twttr.txt.extractHashtags(hashtag);

	    // Should extract the hashtag minus the # sign, hence the .slice(1)
	    return extracted.length === 1 && extracted[0] === hashtag.slice(1);
	  };

	  twttr.txt.isValidUrl = function(url, unicodeDomains, requireProtocol) {
	    if (unicodeDomains == null) {
	      unicodeDomains = true;
	    }

	    if (requireProtocol == null) {
	      requireProtocol = true;
	    }

	    if (!url) {
	      return false;
	    }

	    var urlParts = url.match(twttr.txt.regexen.validateUrlUnencoded);

	    if (!urlParts || urlParts[0] !== url) {
	      return false;
	    }

	    var scheme = urlParts[1],
	        authority = urlParts[2],
	        path = urlParts[3],
	        query = urlParts[4],
	        fragment = urlParts[5];

	    if (!(
	      (!requireProtocol || (isValidMatch(scheme, twttr.txt.regexen.validateUrlScheme) && scheme.match(/^https?$/i))) &&
	      isValidMatch(path, twttr.txt.regexen.validateUrlPath) &&
	      isValidMatch(query, twttr.txt.regexen.validateUrlQuery, true) &&
	      isValidMatch(fragment, twttr.txt.regexen.validateUrlFragment, true)
	    )) {
	      return false;
	    }

	    return (unicodeDomains && isValidMatch(authority, twttr.txt.regexen.validateUrlUnicodeAuthority)) ||
	           (!unicodeDomains && isValidMatch(authority, twttr.txt.regexen.validateUrlAuthority));
	  };

	  function isValidMatch(string, regex, optional) {
	    if (!optional) {
	      // RegExp["$&"] is the text of the last match
	      // blank strings are ok, but are falsy, so we check stringiness instead of truthiness
	      return ((typeof string === "string") && string.match(regex) && RegExp["$&"] === string);
	    }

	    // RegExp["$&"] is the text of the last match
	    return (!string || (string.match(regex) && RegExp["$&"] === string));
	  }

	  if (typeof module != 'undefined' && module.exports) {
	    module.exports = twttr.txt;
	  }

	  if (true) {
	    !(__WEBPACK_AMD_DEFINE_ARRAY__ = [], __WEBPACK_AMD_DEFINE_FACTORY__ = (twttr.txt), __WEBPACK_AMD_DEFINE_RESULT__ = (typeof __WEBPACK_AMD_DEFINE_FACTORY__ === 'function' ? (__WEBPACK_AMD_DEFINE_FACTORY__.apply(exports, __WEBPACK_AMD_DEFINE_ARRAY__)) : __WEBPACK_AMD_DEFINE_FACTORY__), __WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
	  }

	  if (typeof window != 'undefined') {
	    if (window.twttr) {
	      for (var prop in twttr) {
	        window.twttr[prop] = twttr[prop];
	      }
	    } else {
	      window.twttr = twttr;
	    }
	  }
	})();


/***/ },
/* 75 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	exports.default = _PDFJSAnnotate2.default;

/***/ },
/* 76 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _StoreAdapter = __webpack_require__(77);

	var _StoreAdapter2 = _interopRequireDefault(_StoreAdapter);

	var _LocalStoreAdapter = __webpack_require__(95);

	var _LocalStoreAdapter2 = _interopRequireDefault(_LocalStoreAdapter);

	var _render = __webpack_require__(97);

	var _render2 = _interopRequireDefault(_render);

	var _UI = __webpack_require__(107);

	var _UI2 = _interopRequireDefault(_UI);

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _uuid = __webpack_require__(96);

	var _uuid2 = _interopRequireDefault(_uuid);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	exports.default = {
	  findAnnotationAtPoint: _utils.findAnnotationAtPoint,
	  findSVGContainer: _utils.findSVGContainer,
	  convertToScreenPoint: _utils.convertToScreenPoint,

	  /**
	   * Abstract class that needs to be defined so PDFJSAnnotate
	   * knows how to communicate with your server.
	   */
	  StoreAdapter: _StoreAdapter2.default,

	  /**
	   * Implementation of StoreAdapter that stores annotation data to localStorage.
	   */
	  LocalStoreAdapter: _LocalStoreAdapter2.default,

	  /**
	   * Abstract instance of StoreAdapter
	   */
	  __storeAdapter: new _StoreAdapter2.default(),

	  /**
	   * Getter for the underlying StoreAdapter property
	   *
	   * @return {StoreAdapter}
	   */
	  getStoreAdapter: function getStoreAdapter() {
	    return this.__storeAdapter;
	  },


	  /**
	   * Setter for the underlying StoreAdapter property
	   *
	   * @param {StoreAdapter} adapter The StoreAdapter implementation to be used.
	   */
	  setStoreAdapter: function setStoreAdapter(adapter) {
	    // TODO this throws an error when bundled
	    // if (!(adapter instanceof StoreAdapter)) {
	    //   throw new Error('adapter must be an instance of StoreAdapter');
	    // }

	    this.__storeAdapter = adapter;
	  },


	  /**
	   * UI is a helper for instrumenting UI interactions for creating,
	   * editing, and deleting annotations in the browser.
	   */
	  UI: _UI2.default,

	  /**
	   * Render the annotations for a page in the PDF Document
	   *
	   * @param {SVGElement} svg The SVG element that annotations should be rendered to
	   * @param {PageViewport} viewport The PDFPage.getViewport data
	   * @param {Object} data The StoreAdapter.getAnnotations data
	   * @return {Promise}
	   */
	  render: _render2.default,

	  /**
	   * Convenience method for getting annotation data
	   *
	   * @alias StoreAdapter.getAnnotations
	   * @param {String} documentId The ID of the document
	   * @param {String} pageNumber The page number
	   * @return {Promise}
	   */
	  getAnnotations: function getAnnotations(documentId, pageNumber) {
	    var _getStoreAdapter;

	    return (_getStoreAdapter = this.getStoreAdapter()).getAnnotations.apply(_getStoreAdapter, arguments);
	  },


	  config: _config2.default,

	  uuid: _uuid2.default
	};

/***/ },
/* 77 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

	var _abstractFunction = __webpack_require__(78);

	var _abstractFunction2 = _interopRequireDefault(_abstractFunction);

	var _event = __webpack_require__(79);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

	// Adapter should never be invoked publicly
	var StoreAdapter = function () {
	  /**
	   * Create a new StoreAdapter instance
	   *
	   * @param {Object} [definition] The definition to use for overriding abstract methods
	   */
	  function StoreAdapter() {
	    var _this = this;

	    var definition = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};

	    _classCallCheck(this, StoreAdapter);

	    // Copy each function from definition if it is a function we know about
	    Object.keys(definition).forEach(function (key) {
	      if (typeof definition[key] === 'function' && typeof _this[key] === 'function') {
	        _this[key] = definition[key];
	      }
	    });
	  }

	  /**
	   * Get all the annotations for a given document and page number.
	   *
	   * @param {String} documentId The ID for the document the annotations belong to
	   * @param {Number} pageNumber The number of the page the annotations belong to
	   * @return {Promise}
	   */


	  _createClass(StoreAdapter, [{
	    key: '__getAnnotations',
	    value: function __getAnnotations(documentId, pageNumber) {
	      (0, _abstractFunction2.default)('getAnnotations');
	    }
	  }, {
	    key: 'getAnnotation',


	    /**
	     * Get the definition for a specific annotation.
	     *
	     * @param {String} documentId The ID for the document the annotation belongs to
	     * @param {String} annotationId The ID for the annotation
	     * @return {Promise}
	     */
	    value: function getAnnotation(documentId, annotationId) {
	      (0, _abstractFunction2.default)('getAnnotation');
	    }

	    /**
	     * Add an annotation
	     *
	     * @param {String} documentId The ID for the document to add the annotation to
	     * @param {String} pageNumber The page number to add the annotation to
	     * @param {Object} annotation The definition for the new annotation
	     * @return {Promise}
	     */

	  }, {
	    key: '__addAnnotation',
	    value: function __addAnnotation(documentId, pageNumber, annotation) {
	      (0, _abstractFunction2.default)('addAnnotation');
	    }
	  }, {
	    key: '__editAnnotation',


	    /**
	     * Edit an annotation
	     *
	     * @param {String} documentId The ID for the document
	     * @param {String} pageNumber the page number of the annotation
	     * @param {Object} annotation The definition of the modified annotation
	     * @return {Promise}
	     */
	    value: function __editAnnotation(documentId, pageNumber, annotation) {
	      (0, _abstractFunction2.default)('editAnnotation');
	    }
	  }, {
	    key: '__deleteAnnotation',


	    /**
	     * Delete an annotation
	     *
	     * @param {String} documentId The ID for the document
	     * @param {String} annotationId The ID for the annotation
	     * @return {Promise}
	     */
	    value: function __deleteAnnotation(documentId, annotationId) {
	      (0, _abstractFunction2.default)('deleteAnnotation');
	    }
	  }, {
	    key: 'getComments',


	    /**
	     * Get all the comments for an annotation
	     *
	     * @param {String} documentId The ID for the document
	     * @param {String} annotationId The ID for the annotation
	     * @return {Promise}
	     */
	    value: function getComments(documentId, annotationId) {
	      (0, _abstractFunction2.default)('getComments');
	    }

	    /**
	     * Add a new comment
	     *
	     * @param {String} documentId The ID for the document
	     * @param {String} annotationId The ID for the annotation
	     * @param {Object} content The definition of the comment
	     * @return {Promise}
	     */

	  }, {
	    key: '__addComment',
	    value: function __addComment(documentId, annotationId, content) {
	      (0, _abstractFunction2.default)('addComment');
	    }
	  }, {
	    key: '__deleteComment',


	    /**
	     * Delete a comment
	     *
	     * @param {String} documentId The ID for the document
	     * @param {String} commentId The ID for the comment
	     * @return {Promise}
	     */
	    value: function __deleteComment(documentId, commentId) {
	      (0, _abstractFunction2.default)('deleteComment');
	    }
	  }, {
	    key: 'getAnnotations',
	    get: function get() {
	      return this.__getAnnotations;
	    },
	    set: function set(fn) {
	      this.__getAnnotations = function getAnnotations(documentId, pageNumber) {
	        return fn.apply(undefined, arguments).then(function (annotations) {
	          // TODO may be best to have this happen on the server
	          if (annotations.annotations) {
	            annotations.annotations.forEach(function (a) {
	              a.documentId = documentId;
	            });
	          }
	          return annotations;
	        });
	      };
	    }
	  }, {
	    key: 'addAnnotation',
	    get: function get() {
	      return this.__addAnnotation;
	    },
	    set: function set(fn) {
	      this.__addAnnotation = function addAnnotation(documentId, pageNumber, annotation) {
	        return fn.apply(undefined, arguments).then(function (annotation) {
	          (0, _event.fireEvent)('annotation:add', documentId, pageNumber, annotation);
	          return annotation;
	        });
	      };
	    }
	  }, {
	    key: 'editAnnotation',
	    get: function get() {
	      return this.__editAnnotation;
	    },
	    set: function set(fn) {
	      this.__editAnnotation = function editAnnotation(documentId, annotationId, annotation) {
	        return fn.apply(undefined, arguments).then(function (annotation) {
	          (0, _event.fireEvent)('annotation:edit', documentId, annotationId, annotation);
	          return annotation;
	        });
	      };
	    }
	  }, {
	    key: 'deleteAnnotation',
	    get: function get() {
	      return this.__deleteAnnotation;
	    },
	    set: function set(fn) {
	      this.__deleteAnnotation = function deleteAnnotation(documentId, annotationId) {
	        return fn.apply(undefined, arguments).then(function (success) {
	          if (success) {
	            (0, _event.fireEvent)('annotation:delete', documentId, annotationId);
	          }
	          return success;
	        });
	      };
	    }
	  }, {
	    key: 'addComment',
	    get: function get() {
	      return this.__addComment;
	    },
	    set: function set(fn) {
	      this.__addComment = function addComment(documentId, annotationId, content) {
	        return fn.apply(undefined, arguments).then(function (comment) {
	          (0, _event.fireEvent)('comment:add', documentId, annotationId, comment);
	          return comment;
	        });
	      };
	    }
	  }, {
	    key: 'deleteComment',
	    get: function get() {
	      return this.__deleteComment;
	    },
	    set: function set(fn) {
	      this.__deleteComment = function deleteComment(documentId, commentId) {
	        return fn.apply(undefined, arguments).then(function (success) {
	          if (success) {
	            (0, _event.fireEvent)('comment:delete', documentId, commentId);
	          }
	          return success;
	        });
	      };
	    }
	  }]);

	  return StoreAdapter;
	}();

	exports.default = StoreAdapter;

/***/ },
/* 78 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = abstractFunction;
	/**
	 * Throw an Error for an abstract function that hasn't been implemented.
	 *
	 * @param {String} name The name of the abstract function
	 */
	function abstractFunction(name) {
	  throw new Error(name + ' is not implemented');
	}

/***/ },
/* 79 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.fireEvent = fireEvent;
	exports.addEventListener = addEventListener;
	exports.removeEventListener = removeEventListener;

	var _events = __webpack_require__(80);

	var _events2 = _interopRequireDefault(_events);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var emitter = new _events2.default();

	var clickNode = void 0;

	/**
	 * Handle document.click event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	document.addEventListener('click', function handleDocumentClick(e) {
	  if (!(0, _utils.findSVGAtPoint)(e.clientX, e.clientY)) {
	    return;
	  }

	  var target = (0, _utils.findAnnotationAtPoint)(e.clientX, e.clientY);

	  // Emit annotation:blur if clickNode is no longer clicked
	  if (clickNode && clickNode !== target) {
	    emitter.emit('annotation:blur', clickNode);
	  }

	  // Emit annotation:click if target was clicked
	  if (target) {
	    emitter.emit('annotation:click', target);
	  }

	  clickNode = target;
	});

	// let mouseOverNode;
	// document.addEventListener('mousemove', function handleDocumentMousemove(e) {
	//   let target = findAnnotationAtPoint(e.clientX, e.clientY);
	//
	//   // Emit annotation:mouseout if target was mouseout'd
	//   if (mouseOverNode && !target) {
	//     emitter.emit('annotation:mouseout', mouseOverNode);
	//   }
	//
	//   // Emit annotation:mouseover if target was mouseover'd
	//   if (target && mouseOverNode !== target) {
	//     emitter.emit('annotation:mouseover', target);
	//   }
	//
	//   mouseOverNode = target;
	// });

	function fireEvent() {
	  emitter.emit.apply(emitter, arguments);
	};
	function addEventListener() {
	  emitter.on.apply(emitter, arguments);
	};
	function removeEventListener() {
	  emitter.removeListener.apply(emitter, arguments);
	};

/***/ },
/* 80 */
/***/ function(module, exports) {

	// Copyright Joyent, Inc. and other Node contributors.
	//
	// Permission is hereby granted, free of charge, to any person obtaining a
	// copy of this software and associated documentation files (the
	// "Software"), to deal in the Software without restriction, including
	// without limitation the rights to use, copy, modify, merge, publish,
	// distribute, sublicense, and/or sell copies of the Software, and to permit
	// persons to whom the Software is furnished to do so, subject to the
	// following conditions:
	//
	// The above copyright notice and this permission notice shall be included
	// in all copies or substantial portions of the Software.
	//
	// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
	// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
	// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
	// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
	// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
	// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
	// USE OR OTHER DEALINGS IN THE SOFTWARE.

	function EventEmitter() {
	  this._events = this._events || {};
	  this._maxListeners = this._maxListeners || undefined;
	}
	module.exports = EventEmitter;

	// Backwards-compat with node 0.10.x
	EventEmitter.EventEmitter = EventEmitter;

	EventEmitter.prototype._events = undefined;
	EventEmitter.prototype._maxListeners = undefined;

	// By default EventEmitters will print a warning if more than 10 listeners are
	// added to it. This is a useful default which helps finding memory leaks.
	EventEmitter.defaultMaxListeners = 10;

	// Obviously not all Emitters should be limited to 10. This function allows
	// that to be increased. Set to zero for unlimited.
	EventEmitter.prototype.setMaxListeners = function(n) {
	  if (!isNumber(n) || n < 0 || isNaN(n))
	    throw TypeError('n must be a positive number');
	  this._maxListeners = n;
	  return this;
	};

	EventEmitter.prototype.emit = function(type) {
	  var er, handler, len, args, i, listeners;

	  if (!this._events)
	    this._events = {};

	  // If there is no 'error' event listener then throw.
	  if (type === 'error') {
	    if (!this._events.error ||
	        (isObject(this._events.error) && !this._events.error.length)) {
	      er = arguments[1];
	      if (er instanceof Error) {
	        throw er; // Unhandled 'error' event
	      } else {
	        // At least give some kind of context to the user
	        var err = new Error('Uncaught, unspecified "error" event. (' + er + ')');
	        err.context = er;
	        throw err;
	      }
	    }
	  }

	  handler = this._events[type];

	  if (isUndefined(handler))
	    return false;

	  if (isFunction(handler)) {
	    switch (arguments.length) {
	      // fast cases
	      case 1:
	        handler.call(this);
	        break;
	      case 2:
	        handler.call(this, arguments[1]);
	        break;
	      case 3:
	        handler.call(this, arguments[1], arguments[2]);
	        break;
	      // slower
	      default:
	        args = Array.prototype.slice.call(arguments, 1);
	        handler.apply(this, args);
	    }
	  } else if (isObject(handler)) {
	    args = Array.prototype.slice.call(arguments, 1);
	    listeners = handler.slice();
	    len = listeners.length;
	    for (i = 0; i < len; i++)
	      listeners[i].apply(this, args);
	  }

	  return true;
	};

	EventEmitter.prototype.addListener = function(type, listener) {
	  var m;

	  if (!isFunction(listener))
	    throw TypeError('listener must be a function');

	  if (!this._events)
	    this._events = {};

	  // To avoid recursion in the case that type === "newListener"! Before
	  // adding it to the listeners, first emit "newListener".
	  if (this._events.newListener)
	    this.emit('newListener', type,
	              isFunction(listener.listener) ?
	              listener.listener : listener);

	  if (!this._events[type])
	    // Optimize the case of one listener. Don't need the extra array object.
	    this._events[type] = listener;
	  else if (isObject(this._events[type]))
	    // If we've already got an array, just append.
	    this._events[type].push(listener);
	  else
	    // Adding the second element, need to change to array.
	    this._events[type] = [this._events[type], listener];

	  // Check for listener leak
	  if (isObject(this._events[type]) && !this._events[type].warned) {
	    if (!isUndefined(this._maxListeners)) {
	      m = this._maxListeners;
	    } else {
	      m = EventEmitter.defaultMaxListeners;
	    }

	    if (m && m > 0 && this._events[type].length > m) {
	      this._events[type].warned = true;
	      console.error('(node) warning: possible EventEmitter memory ' +
	                    'leak detected. %d listeners added. ' +
	                    'Use emitter.setMaxListeners() to increase limit.',
	                    this._events[type].length);
	      if (typeof console.trace === 'function') {
	        // not supported in IE 10
	        console.trace();
	      }
	    }
	  }

	  return this;
	};

	EventEmitter.prototype.on = EventEmitter.prototype.addListener;

	EventEmitter.prototype.once = function(type, listener) {
	  if (!isFunction(listener))
	    throw TypeError('listener must be a function');

	  var fired = false;

	  function g() {
	    this.removeListener(type, g);

	    if (!fired) {
	      fired = true;
	      listener.apply(this, arguments);
	    }
	  }

	  g.listener = listener;
	  this.on(type, g);

	  return this;
	};

	// emits a 'removeListener' event iff the listener was removed
	EventEmitter.prototype.removeListener = function(type, listener) {
	  var list, position, length, i;

	  if (!isFunction(listener))
	    throw TypeError('listener must be a function');

	  if (!this._events || !this._events[type])
	    return this;

	  list = this._events[type];
	  length = list.length;
	  position = -1;

	  if (list === listener ||
	      (isFunction(list.listener) && list.listener === listener)) {
	    delete this._events[type];
	    if (this._events.removeListener)
	      this.emit('removeListener', type, listener);

	  } else if (isObject(list)) {
	    for (i = length; i-- > 0;) {
	      if (list[i] === listener ||
	          (list[i].listener && list[i].listener === listener)) {
	        position = i;
	        break;
	      }
	    }

	    if (position < 0)
	      return this;

	    if (list.length === 1) {
	      list.length = 0;
	      delete this._events[type];
	    } else {
	      list.splice(position, 1);
	    }

	    if (this._events.removeListener)
	      this.emit('removeListener', type, listener);
	  }

	  return this;
	};

	EventEmitter.prototype.removeAllListeners = function(type) {
	  var key, listeners;

	  if (!this._events)
	    return this;

	  // not listening for removeListener, no need to emit
	  if (!this._events.removeListener) {
	    if (arguments.length === 0)
	      this._events = {};
	    else if (this._events[type])
	      delete this._events[type];
	    return this;
	  }

	  // emit removeListener for all listeners on all events
	  if (arguments.length === 0) {
	    for (key in this._events) {
	      if (key === 'removeListener') continue;
	      this.removeAllListeners(key);
	    }
	    this.removeAllListeners('removeListener');
	    this._events = {};
	    return this;
	  }

	  listeners = this._events[type];

	  if (isFunction(listeners)) {
	    this.removeListener(type, listeners);
	  } else if (listeners) {
	    // LIFO order
	    while (listeners.length)
	      this.removeListener(type, listeners[listeners.length - 1]);
	  }
	  delete this._events[type];

	  return this;
	};

	EventEmitter.prototype.listeners = function(type) {
	  var ret;
	  if (!this._events || !this._events[type])
	    ret = [];
	  else if (isFunction(this._events[type]))
	    ret = [this._events[type]];
	  else
	    ret = this._events[type].slice();
	  return ret;
	};

	EventEmitter.prototype.listenerCount = function(type) {
	  if (this._events) {
	    var evlistener = this._events[type];

	    if (isFunction(evlistener))
	      return 1;
	    else if (evlistener)
	      return evlistener.length;
	  }
	  return 0;
	};

	EventEmitter.listenerCount = function(emitter, type) {
	  return emitter.listenerCount(type);
	};

	function isFunction(arg) {
	  return typeof arg === 'function';
	}

	function isNumber(arg) {
	  return typeof arg === 'number';
	}

	function isObject(arg) {
	  return typeof arg === 'object' && arg !== null;
	}

	function isUndefined(arg) {
	  return arg === void 0;
	}


/***/ },
/* 81 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.BORDER_COLOR = undefined;
	exports.findSVGContainer = findSVGContainer;
	exports.findSVGAtPoint = findSVGAtPoint;
	exports.findAnnotationAtPoint = findAnnotationAtPoint;
	exports.pointIntersectsRect = pointIntersectsRect;
	exports.getOffsetAnnotationRect = getOffsetAnnotationRect;
	exports.scaleUp = scaleUp;
	exports.convertToSvgRect = convertToSvgRect;
	exports.convertToSvgPoint = convertToSvgPoint;
	exports.convertToScreenPoint = convertToScreenPoint;
	exports.scaleDown = scaleDown;
	exports.getScroll = getScroll;
	exports.getOffset = getOffset;
	exports.disableUserSelect = disableUserSelect;
	exports.enableUserSelect = enableUserSelect;
	exports.getMetadata = getMetadata;

	var _createStylesheet = __webpack_require__(82);

	var _createStylesheet2 = _interopRequireDefault(_createStylesheet);

	var _appendChild = __webpack_require__(83);

	var _mathUtils = __webpack_require__(94);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var BORDER_COLOR = exports.BORDER_COLOR = '#00BFFF';

	var userSelectStyleSheet = (0, _createStylesheet2.default)({
	  body: {
	    '-webkit-user-select': 'none',
	    '-moz-user-select': 'none',
	    '-ms-user-select': 'none',
	    'user-select': 'none'
	  }
	});
	userSelectStyleSheet.setAttribute('data-pdf-annotate-user-select', 'true');

	/**
	 * Find the SVGElement that contains all the annotations for a page
	 *
	 * @param {Element} node An annotation within that container
	 * @return {SVGElement} The container SVG or null if it can't be found
	 */
	function findSVGContainer(node) {
	  var parentNode = node;

	  while ((parentNode = parentNode.parentNode) && parentNode !== document) {
	    if (parentNode.nodeName.toUpperCase() === 'SVG' && parentNode.getAttribute('data-pdf-annotate-container') === 'true') {
	      return parentNode;
	    }
	  }

	  return null;
	}

	/**
	 * Find an SVGElement container at a given point
	 *
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 * @return {SVGElement} The container SVG or null if one can't be found
	 */
	function findSVGAtPoint(x, y) {
	  var elements = document.querySelectorAll('svg[data-pdf-annotate-container="true"]');

	  for (var i = 0, l = elements.length; i < l; i++) {
	    var el = elements[i];
	    var rect = el.getBoundingClientRect();

	    if (pointIntersectsRect(x, y, rect)) {
	      return el;
	    }
	  }

	  return null;
	}

	/**
	 * Find an Element that represents an annotation at a given point.
	 * 
	 * IMPORTANT: Requires the annotation layer to be the top most element so
	 *            either use z-ordering or make it the leaf container.
	 *
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 * @return {Element} The annotation element or null if one can't be found
	 */
	function findAnnotationAtPoint(x, y) {
	  var el = null;
	  var candidate = document.elementFromPoint(x, y);
	  while (!el && candidate && candidate !== document) {
	    var type = candidate.getAttribute('data-pdf-annotate-type');
	    if (type) {
	      el = candidate;
	    }
	    candidate = candidate.parentNode;
	  }
	  return el;
	}

	/**
	 * Determine if a point intersects a rect
	 *
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 * @param {Object} rect The points of a rect (likely from getBoundingClientRect)
	 * @return {Boolean} True if a collision occurs, otherwise false
	 */
	function pointIntersectsRect(x, y, rect) {
	  return y >= rect.top && y <= rect.bottom && x >= rect.left && x <= rect.right;
	}

	/**
	 * Get the rect of an annotation element accounting for offset.
	 *
	 * @param {Element} el The element to get the rect of
	 * @return {Object} The dimensions of the element
	 */
	function getOffsetAnnotationRect(el) {
	  var rect = el.getBoundingClientRect();

	  var _getOffset = getOffset(el),
	      offsetLeft = _getOffset.offsetLeft,
	      offsetTop = _getOffset.offsetTop;

	  return {
	    top: rect.top - offsetTop,
	    left: rect.left - offsetLeft,
	    right: rect.right - offsetLeft,
	    bottom: rect.bottom - offsetTop,
	    width: rect.width,
	    height: rect.height
	  };
	}

	/**
	 * Adjust scale from normalized scale (100%) to rendered scale.
	 *
	 * @param {SVGElement} svg The SVG to gather metadata from
	 * @param {Object} rect A map of numeric values to scale
	 * @return {Object} A copy of `rect` with values scaled up
	 */
	function scaleUp(svg, rect) {
	  var result = {};

	  var _getMetadata = getMetadata(svg),
	      viewport = _getMetadata.viewport;

	  Object.keys(rect).forEach(function (key) {
	    result[key] = rect[key] * viewport.scale;
	  });

	  return result;
	}

	function convertToSvgRect(rect, svg, viewport) {
	  var pt1 = [rect.x, rect.y];
	  var pt2 = [rect.x + rect.width, rect.y + rect.height];

	  pt1 = convertToSvgPoint(pt1, svg, viewport);
	  pt2 = convertToSvgPoint(pt2, svg, viewport);

	  return {
	    x: Math.min(pt1[0], pt2[0]),
	    y: Math.min(pt1[1], pt2[1]),
	    width: Math.abs(pt2[0] - pt1[0]),
	    height: Math.abs(pt2[1] - pt1[1])
	  };
	}

	function convertToSvgPoint(pt, svg, viewport) {
	  var result = {};
	  viewport = viewport || getMetadata(svg).viewport;

	  var xform = [1, 0, 0, 1, 0, 0];
	  xform = (0, _mathUtils.scale)(xform, viewport.scale, viewport.scale);
	  xform = (0, _mathUtils.rotate)(xform, viewport.rotation);

	  var offset = (0, _appendChild.getTranslation)(viewport);
	  xform = (0, _mathUtils.translate)(xform, offset.x, offset.y);

	  return (0, _mathUtils.applyInverseTransform)(pt, xform);
	}

	function convertToScreenPoint(pt, svg, viewport) {
	  var result = {};
	  viewport = viewport || getMetadata(svg).viewport;

	  var xform = [1, 0, 0, 1, 0, 0];
	  xform = (0, _mathUtils.scale)(xform, viewport.scale, viewport.scale);
	  xform = (0, _mathUtils.rotate)(xform, viewport.rotation);

	  var offset = (0, _appendChild.getTranslation)(viewport);
	  xform = (0, _mathUtils.translate)(xform, offset.x, offset.y);

	  return (0, _mathUtils.applyTransform)(pt, xform);
	}

	/**
	 * Adjust scale from rendered scale to a normalized scale (100%).
	 *
	 * @param {SVGElement} svg The SVG to gather metadata from
	 * @param {Object} rect A map of numeric values to scale
	 * @return {Object} A copy of `rect` with values scaled down
	 */
	function scaleDown(svg, rect) {
	  var result = {};

	  var _getMetadata2 = getMetadata(svg),
	      viewport = _getMetadata2.viewport;

	  Object.keys(rect).forEach(function (key) {
	    result[key] = rect[key] / viewport.scale;
	  });

	  return result;
	}

	/**
	 * Get the scroll position of an element, accounting for parent elements
	 *
	 * @param {Element} el The element to get the scroll position for
	 * @return {Object} The scrollTop and scrollLeft position
	 */
	function getScroll(el) {
	  var scrollTop = 0;
	  var scrollLeft = 0;
	  var parentNode = el;

	  while ((parentNode = parentNode.parentNode) && parentNode !== document) {
	    scrollTop += parentNode.scrollTop;
	    scrollLeft += parentNode.scrollLeft;
	  }

	  return { scrollTop: scrollTop, scrollLeft: scrollLeft };
	}

	/**
	 * Get the offset position of an element, accounting for parent elements
	 *
	 * @param {Element} el The element to get the offset position for
	 * @return {Object} The offsetTop and offsetLeft position
	 */
	function getOffset(el) {
	  var parentNode = el;

	  while ((parentNode = parentNode.parentNode) && parentNode !== document) {
	    if (parentNode.nodeName.toUpperCase() === 'SVG') {
	      break;
	    }
	  }

	  var rect = parentNode.getBoundingClientRect();

	  return { offsetLeft: rect.left, offsetTop: rect.top };
	}

	/**
	 * Disable user ability to select text on page
	 */
	function disableUserSelect() {
	  if (!userSelectStyleSheet.parentNode) {
	    document.head.appendChild(userSelectStyleSheet);
	  }
	}

	/**
	 * Enable user ability to select text on page
	 */
	function enableUserSelect() {
	  if (userSelectStyleSheet.parentNode) {
	    userSelectStyleSheet.parentNode.removeChild(userSelectStyleSheet);
	  }
	}

	/**
	 * Get the metadata for a SVG container
	 *
	 * @param {SVGElement} svg The SVG container to get metadata for
	 */
	function getMetadata(svg) {
	  return {
	    documentId: svg.getAttribute('data-pdf-annotate-document'),
	    pageNumber: parseInt(svg.getAttribute('data-pdf-annotate-page'), 10),
	    viewport: JSON.parse(svg.getAttribute('data-pdf-annotate-viewport'))
	  };
	}

/***/ },
/* 82 */
/***/ function(module, exports) {

	module.exports = function createStyleSheet(blocks) {
	  var style = document.createElement('style');
	  var text = Object.keys(blocks).map(function (selector) {
	    return processRuleSet(selector, blocks[selector]);
	  }).join('\n');
	  
	  style.setAttribute('type', 'text/css');
	  style.appendChild(document.createTextNode(text));

	  return style;
	}

	function processRuleSet(selector, block) {
	  return selector + ' {\n' + processDeclarationBlock(block) + '\n}';
	}

	function processDeclarationBlock(block) {
	  return Object.keys(block).map(function (prop) {
	    return processDeclaration(prop, block[prop]);
	  }).join('\n');
	}

	function processDeclaration(prop, value) {
	  if (!isNaN(value) && value != 0) {
	    value = value + 'px';
	  }

	  return hyphenate(prop) + ': ' + value + ';';
	}

	function hyphenate(prop) {
	  return prop.replace(/[A-Z]/g, function (match) {
	    return '-' + match.toLowerCase();
	  });
	}


/***/ },
/* 83 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.getTranslation = getTranslation;
	exports.appendChild = appendChild;
	exports.transformChild = transformChild;

	var _objectAssign = __webpack_require__(84);

	var _objectAssign2 = _interopRequireDefault(_objectAssign);

	var _renderLine = __webpack_require__(85);

	var _renderLine2 = _interopRequireDefault(_renderLine);

	var _renderPath = __webpack_require__(88);

	var _renderPath2 = _interopRequireDefault(_renderPath);

	var _renderPoint = __webpack_require__(89);

	var _renderPoint2 = _interopRequireDefault(_renderPoint);

	var _renderRect = __webpack_require__(90);

	var _renderRect2 = _interopRequireDefault(_renderRect);

	var _renderText = __webpack_require__(91);

	var _renderText2 = _interopRequireDefault(_renderText);

	var _renderCircle = __webpack_require__(92);

	var _renderCircle2 = _interopRequireDefault(_renderCircle);

	var _renderArrow = __webpack_require__(93);

	var _renderArrow2 = _interopRequireDefault(_renderArrow);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var isFirefox = /firefox/i.test(navigator.userAgent);

	/**
	 * Get the x/y translation to be used for transforming the annotations
	 * based on the rotation of the viewport.
	 *
	 * @param {Object} viewport The viewport data from the page
	 * @return {Object}
	 */
	function getTranslation(viewport) {
	  var x = void 0;
	  var y = void 0;

	  // Modulus 360 on the rotation so that we only
	  // have to worry about four possible values.
	  switch (viewport.rotation % 360) {
	    case 0:
	      x = y = 0;
	      break;
	    case 90:
	      x = 0;
	      y = viewport.width / viewport.scale * -1;
	      break;
	    case 180:
	      x = viewport.width / viewport.scale * -1;
	      y = viewport.height / viewport.scale * -1;
	      break;
	    case 270:
	      x = viewport.height / viewport.scale * -1;
	      y = 0;
	      break;
	  }

	  return { x: x, y: y };
	}

	/**
	 * Transform the rotation and scale of a node using SVG's native transform attribute.
	 *
	 * @param {Node} node The node to be transformed
	 * @param {Object} viewport The page's viewport data
	 * @return {Node}
	 */
	function transform(node, viewport) {
	  var trans = getTranslation(viewport);

	  // Let SVG natively transform the element
	  node.setAttribute('transform', 'scale(' + viewport.scale + ') rotate(' + viewport.rotation + ') translate(' + trans.x + ', ' + trans.y + ')');

	  // Manually adjust x/y for nested SVG nodes
	  if (!isFirefox && node.nodeName.toLowerCase() === 'svg') {
	    node.setAttribute('x', parseInt(node.getAttribute('x'), 10) * viewport.scale);
	    node.setAttribute('y', parseInt(node.getAttribute('y'), 10) * viewport.scale);

	    var x = parseInt(node.getAttribute('x', 10));
	    var y = parseInt(node.getAttribute('y', 10));
	    var width = parseInt(node.getAttribute('width'), 10);
	    var height = parseInt(node.getAttribute('height'), 10);
	    var path = node.querySelector('path');
	    var svg = path.parentNode;

	    // Scale width/height
	    [node, svg, path, node.querySelector('rect')].forEach(function (n) {
	      n.setAttribute('width', parseInt(n.getAttribute('width'), 10) * viewport.scale);
	      n.setAttribute('height', parseInt(n.getAttribute('height'), 10) * viewport.scale);
	    });

	    // Transform path but keep scale at 100% since it will be handled natively
	    transform(path, (0, _objectAssign2.default)({}, viewport, { scale: 1 }));

	    switch (viewport.rotation % 360) {
	      case 90:
	        node.setAttribute('x', viewport.width - y - width);
	        node.setAttribute('y', x);
	        svg.setAttribute('x', 1);
	        svg.setAttribute('y', 0);
	        break;
	      case 180:
	        node.setAttribute('x', viewport.width - x - width);
	        node.setAttribute('y', viewport.height - y - height);
	        svg.setAttribute('y', 2);
	        break;
	      case 270:
	        node.setAttribute('x', y);
	        node.setAttribute('y', viewport.height - x - height);
	        svg.setAttribute('x', -1);
	        svg.setAttribute('y', 0);
	        break;
	    }
	  }

	  return node;
	}

	/**
	 * Append an annotation as a child of an SVG.
	 *
	 * @param {SVGElement} svg The SVG element to append the annotation to
	 * @param {Object} annotation The annotation definition to render and append
	 * @param {Object} viewport The page's viewport data
	 * @return {SVGElement} A node that was created and appended by this function
	 */
	function appendChild(svg, annotation, viewport) {
	  if (!viewport) {
	    viewport = JSON.parse(svg.getAttribute('data-pdf-annotate-viewport'));
	  }

	  var child = void 0;
	  switch (annotation.type) {
	    case 'area':
	    case 'highlight':
	      child = (0, _renderRect2.default)(annotation);
	      break;
	    case 'circle':
	    case 'fillcircle':
	    case 'emptycircle':
	      child = (0, _renderCircle2.default)(annotation);
	      break;
	    case 'strikeout':
	      child = (0, _renderLine2.default)(annotation);
	      break;
	    case 'point':
	      child = (0, _renderPoint2.default)(annotation);
	      break;
	    case 'textbox':
	      child = (0, _renderText2.default)(annotation);
	      break;
	    case 'drawing':
	      child = (0, _renderPath2.default)(annotation);
	      break;
	    case 'arrow':
	      child = (0, _renderArrow2.default)(annotation);
	      break;
	  }

	  // If no type was provided for an annotation it will result in node being null.
	  // Skip appending/transforming if node doesn't exist.
	  if (child) {
	    // Set attributes
	    child.setAttribute('data-pdf-annotate-id', annotation.uuid);
	    child.setAttribute('data-pdf-annotate-type', annotation.type);
	    child.setAttribute('aria-hidden', true);

	    svg.appendChild(transform(child, viewport));
	  }

	  return child;
	}

	/**
	 * Transform a child annotation of an SVG.
	 *
	 * @param {SVGElement} svg The SVG element with the child annotation
	 * @param {Object} child The SVG child to transform
	 * @param {Object} viewport The page's viewport data
	 * @return {SVGElement} A node that was transformed by this function
	 */
	function transformChild(svg, child, viewport) {
	  if (!viewport) {
	    viewport = JSON.parse(svg.getAttribute('data-pdf-annotate-viewport'));
	  }

	  // If no type was provided for an annotation it will result in node being null.
	  // Skip transforming if node doesn't exist.
	  if (child) {
	    child = transform(child, viewport);
	  }

	  return child;
	}

	exports.default = {
	  /**
	   * Get the x/y translation to be used for transforming the annotations
	   * based on the rotation of the viewport.
	   */
	  getTranslation: getTranslation,

	  /**
	   * Append an SVG child for an annotation
	   */
	  appendChild: appendChild,

	  /**
	   * Transform an existing SVG child
	   */
	  transformChild: transformChild
	};

/***/ },
/* 84 */
/***/ function(module, exports) {

	/*
	object-assign
	(c) Sindre Sorhus
	@license MIT
	*/

	'use strict';
	/* eslint-disable no-unused-vars */
	var getOwnPropertySymbols = Object.getOwnPropertySymbols;
	var hasOwnProperty = Object.prototype.hasOwnProperty;
	var propIsEnumerable = Object.prototype.propertyIsEnumerable;

	function toObject(val) {
		if (val === null || val === undefined) {
			throw new TypeError('Object.assign cannot be called with null or undefined');
		}

		return Object(val);
	}

	function shouldUseNative() {
		try {
			if (!Object.assign) {
				return false;
			}

			// Detect buggy property enumeration order in older V8 versions.

			// https://bugs.chromium.org/p/v8/issues/detail?id=4118
			var test1 = new String('abc');  // eslint-disable-line no-new-wrappers
			test1[5] = 'de';
			if (Object.getOwnPropertyNames(test1)[0] === '5') {
				return false;
			}

			// https://bugs.chromium.org/p/v8/issues/detail?id=3056
			var test2 = {};
			for (var i = 0; i < 10; i++) {
				test2['_' + String.fromCharCode(i)] = i;
			}
			var order2 = Object.getOwnPropertyNames(test2).map(function (n) {
				return test2[n];
			});
			if (order2.join('') !== '0123456789') {
				return false;
			}

			// https://bugs.chromium.org/p/v8/issues/detail?id=3056
			var test3 = {};
			'abcdefghijklmnopqrst'.split('').forEach(function (letter) {
				test3[letter] = letter;
			});
			if (Object.keys(Object.assign({}, test3)).join('') !==
					'abcdefghijklmnopqrst') {
				return false;
			}

			return true;
		} catch (err) {
			// We don't expect any of the above to throw, but better to be safe.
			return false;
		}
	}

	module.exports = shouldUseNative() ? Object.assign : function (target, source) {
		var from;
		var to = toObject(target);
		var symbols;

		for (var s = 1; s < arguments.length; s++) {
			from = Object(arguments[s]);

			for (var key in from) {
				if (hasOwnProperty.call(from, key)) {
					to[key] = from[key];
				}
			}

			if (getOwnPropertySymbols) {
				symbols = getOwnPropertySymbols(from);
				for (var i = 0; i < symbols.length; i++) {
					if (propIsEnumerable.call(from, symbols[i])) {
						to[symbols[i]] = from[symbols[i]];
					}
				}
			}
		}

		return to;
	};


/***/ },
/* 85 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderLine;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	var _normalizeColor = __webpack_require__(87);

	var _normalizeColor2 = _interopRequireDefault(_normalizeColor);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Create SVGLineElements from an annotation definition.
	 * This is used for anntations of type `strikeout`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGGElement} A group of all lines to be rendered
	 */
	function renderLine(a) {
	  var group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
	  (0, _setAttributes2.default)(group, {
	    stroke: (0, _normalizeColor2.default)(a.color || '#f00'),
	    strokeWidth: 1
	  });

	  a.rectangles.forEach(function (r) {
	    var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');

	    (0, _setAttributes2.default)(line, {
	      x1: r.x,
	      y1: r.y,
	      x2: r.x + r.width,
	      y2: r.y
	    });

	    group.appendChild(line);
	  });

	  return group;
	}

/***/ },
/* 86 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = setAttributes;
	var UPPER_REGEX = /[A-Z]/g;

	// Don't convert these attributes from camelCase to hyphenated-attributes
	var BLACKLIST = ['viewBox'];

	var keyCase = function keyCase(key) {
	  if (BLACKLIST.indexOf(key) === -1) {
	    key = key.replace(UPPER_REGEX, function (match) {
	      return '-' + match.toLowerCase();
	    });
	  }
	  return key;
	};

	/**
	 * Set attributes for a node from a map
	 *
	 * @param {Node} node The node to set attributes on
	 * @param {Object} attributes The map of key/value pairs to use for attributes
	 */
	function setAttributes(node, attributes) {
	  Object.keys(attributes).forEach(function (key) {
	    node.setAttribute(keyCase(key), attributes[key]);
	  });
	}

/***/ },
/* 87 */
/***/ function(module, exports) {

	"use strict";

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = normalizeColor;
	var REGEX_HASHLESS_HEX = /^([a-f0-9]{6}|[a-f0-9]{3})$/i;

	/**
	 * Normalize a color value
	 *
	 * @param {String} color The color to normalize
	 * @return {String}
	 */
	function normalizeColor(color) {
	  if (REGEX_HASHLESS_HEX.test(color)) {
	    color = "#" + color;
	  }
	  return color;
	}

/***/ },
/* 88 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderPath;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	var _normalizeColor = __webpack_require__(87);

	var _normalizeColor2 = _interopRequireDefault(_normalizeColor);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Create SVGPathElement from an annotation definition.
	 * This is used for anntations of type `drawing`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGPathElement} The path to be rendered
	 */
	function renderPath(a) {
	  var d = [];
	  var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

	  for (var i = 0, l = a.lines.length; i < l; i++) {
	    var p1 = a.lines[i];
	    var p2 = a.lines[i + 1];
	    if (p2) {
	      d.push('M' + p1[0] + ' ' + p1[1] + ' ' + p2[0] + ' ' + p2[1]);
	    }
	  }

	  /*
	    
	     if(a.lines.length>2) {
	      var p1 = a.lines[0];
	      var p2 = a.lines[a.lines.length-1];
	  
	      var p3 = []; //arrow 
	      var p4 = [];
	      var p0 = []; //arrow intersection
	  
	  
	   
	      if (p2) {
	        var k = -(p2[0]-p1[0])/(p2[1]-p1[1]);
	  
	        var deltaX = 3;
	        p0[0] = p1[0]+0.8*(p2[0]-p1[0]);
	        p0[1] = p1[1]+0.8*(p2[1]-p1[1]);
	  
	        p3[0] = p0[0] + deltaX;
	        p3[1] = p0[1] + k*deltaX;
	  
	        p4[0] = p0[0] - deltaX;
	        p4[1] = p0[1] - k*deltaX;
	  
	        if(Math.abs(p2[1]-p1[1]) < 20) {
	  
	          p3[0] = p0[0] ;
	          p3[1] = p0[1] + deltaX*1;
	  
	          p4[0] = p0[0] ;
	          p4[1] = p0[1] - deltaX*1;
	  
	        }
	  
	        d.push(`M${p1[0]} ${p1[1]} ${p2[0]} ${p2[1]}`);
	         //d.push(`M${p1[0]} ${p1[1]} ${p2[0]} ${p2[1]}`);
	        d.push(`M${p2[0]} ${p2[1]} ${p3[0]} ${p3[1]}`);
	        d.push(`M${p3[0]} ${p3[1]} ${p4[0]} ${p4[1]}`);
	        d.push(`M${p4[0]} ${p4[1]} ${p2[0]} ${p2[1]}`);
	       }
	      }*/

	  (0, _setAttributes2.default)(path, {
	    d: d.join(' ') + 'Z',
	    stroke: (0, _normalizeColor2.default)(a.color || '#000'),
	    strokeWidth: a.width || 1,
	    fill: 'none'
	  });

	  return path;
	}

/***/ },
/* 89 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderPoint;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var SIZE = 25;
	var D = 'M499.968 214.336q-113.832 0 -212.877 38.781t-157.356 104.625 -58.311 142.29q0 62.496 39.897 119.133t112.437 97.929l48.546 27.9 -15.066 53.568q-13.392 50.778 -39.06 95.976 84.816 -35.154 153.45 -95.418l23.994 -21.204 31.806 3.348q38.502 4.464 72.54 4.464 113.832 0 212.877 -38.781t157.356 -104.625 58.311 -142.29 -58.311 -142.29 -157.356 -104.625 -212.877 -38.781z';

	/**
	 * Create SVGElement from an annotation definition.
	 * This is used for anntations of type `comment`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGElement} A svg to be rendered
	 */
	function renderPoint(a) {
	  var outerSVG = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	  var innerSVG = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	  var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
	  var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

	  (0, _setAttributes2.default)(outerSVG, {
	    width: SIZE,
	    height: SIZE,
	    x: a.x,
	    y: a.y
	  });

	  (0, _setAttributes2.default)(innerSVG, {
	    width: SIZE,
	    height: SIZE,
	    x: 0,
	    y: SIZE * 0.05 * -1,
	    viewBox: '0 0 1000 1000'
	  });

	  (0, _setAttributes2.default)(rect, {
	    width: SIZE,
	    height: SIZE,
	    stroke: '#000',
	    fill: '#ff0'
	  });

	  (0, _setAttributes2.default)(path, {
	    d: D,
	    strokeWidth: 50,
	    stroke: '#000',
	    fill: '#fff'
	  });

	  innerSVG.appendChild(path);
	  outerSVG.appendChild(rect);
	  outerSVG.appendChild(innerSVG);

	  return outerSVG;
	}

/***/ },
/* 90 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderRect;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	var _normalizeColor = __webpack_require__(87);

	var _normalizeColor2 = _interopRequireDefault(_normalizeColor);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Create SVGRectElements from an annotation definition.
	 * This is used for anntations of type `area` and `highlight`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGGElement|SVGRectElement} A group of all rects to be rendered
	 */
	function renderRect(a) {
	  if (a.type === 'highlight') {
	    var group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
	    (0, _setAttributes2.default)(group, {
	      fill: (0, _normalizeColor2.default)(a.color || '#ff0'),
	      fillOpacity: 0.2
	    });

	    a.rectangles.forEach(function (r) {
	      group.appendChild(createRect(r));
	    });

	    return group;
	  } else {
	    var rect = createRect(a);
	    (0, _setAttributes2.default)(rect, {
	      stroke: (0, _normalizeColor2.default)(a.color || '#f00'),
	      fill: 'none'
	    });

	    return rect;
	  }
	}

	function createRect(r) {
	  var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');

	  (0, _setAttributes2.default)(rect, {
	    x: r.x,
	    y: r.y,
	    width: r.width,
	    height: r.height
	  });

	  return rect;
	}

/***/ },
/* 91 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderText;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	var _normalizeColor = __webpack_require__(87);

	var _normalizeColor2 = _interopRequireDefault(_normalizeColor);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Create SVGTextElement from an annotation definition.
	 * This is used for anntations of type `textbox`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGTextElement} A text to be rendered
	 */
	function renderText(a) {

	  // Text should be rendered at 0 degrees relative to
	  // document rotation
	  var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
	  var x = a.x;
	  var y = a.y;

	  (0, _setAttributes2.default)(text, {
	    x: x,
	    y: y,
	    fill: (0, _normalizeColor2.default)(a.color || '#000'),
	    fontSize: a.size,
	    transform: 'rotate(' + a.rotation + ', ' + x + ', ' + y + ')'
	  });
	  text.innerHTML = a.content;

	  var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
	  g.appendChild(text);

	  return g;
	}

/***/ },
/* 92 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderCircle;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	var _normalizeColor = __webpack_require__(87);

	var _normalizeColor2 = _interopRequireDefault(_normalizeColor);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Create an SVGCircleElement from an annotation definition.
	 * This is used for annotations of type `circle`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGGElement|SVGCircleElement} A circle to be rendered
	 */
	function renderCircle(a) {
	  var circle = createCircle(a);
	  var color = (0, _normalizeColor2.default)(a.color || '#f00');

	  if (a.type === 'circle') (0, _setAttributes2.default)(circle, {
	    stroke: color,
	    fill: 'none',
	    'stroke-width': 5
	  });
	  if (a.type === 'emptycircle') (0, _setAttributes2.default)(circle, {
	    stroke: color,
	    fill: 'none',
	    'stroke-width': 2
	  });

	  if (a.type === 'fillcircle') (0, _setAttributes2.default)(circle, {
	    stroke: color,
	    fill: color,
	    'stroke-width': 5
	  });

	  return circle;
	}

	function createCircle(a) {
	  var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
	  (0, _setAttributes2.default)(circle, {
	    cx: a.cx,
	    cy: a.cy,
	    r: a.r
	  });

	  return circle;
	}

/***/ },
/* 93 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderArrow;

	var _setAttributes = __webpack_require__(86);

	var _setAttributes2 = _interopRequireDefault(_setAttributes);

	var _normalizeColor = __webpack_require__(87);

	var _normalizeColor2 = _interopRequireDefault(_normalizeColor);

	var _mathUtils = __webpack_require__(94);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Create SVGPathElement from an annotation definition.
	 * This is used for anntations of type `drawing`.
	 *
	 * @param {Object} a The annotation definition
	 * @return {SVGPathElement} The path to be rendered
	 */
	function renderArrow(a) {
	  var d = [];
	  var arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');

	  if (a.lines.length == 2) {
	    var p1 = a.lines[0];
	    var p2 = a.lines[a.lines.length - 1];

	    var arrowLength = 40;
	    var pt0 = (0, _mathUtils.makePoint)(p1[0], p1[1], 0);
	    var pt1 = (0, _mathUtils.makePoint)(p2[0], p2[1], 0);
	    var x = (0, _mathUtils.makeVectorFromPoints)(pt0, pt1);
	    var unitX = (0, _mathUtils.unitVector)(x);
	    pt1 = (0, _mathUtils.addVector)(pt0, (0, _mathUtils.multiplyVector)(unitX, arrowLength));
	    x = (0, _mathUtils.makeVectorFromPoints)(pt0, pt1);
	    var unitZ = (0, _mathUtils.makeVector)(0, 0, 1);
	    var unitY = (0, _mathUtils.unitVector)((0, _mathUtils.crossProduct)(unitX, unitZ));
	    var thickness = a.width || 10;

	    var A = (0, _mathUtils.addVector)(pt0, (0, _mathUtils.multiplyVector)(unitY, thickness * 0.5));
	    var B = (0, _mathUtils.addVector)(A, (0, _mathUtils.multiplyVector)(unitX, (0, _mathUtils.magnitude)(x) - thickness * 2.0));
	    var C = (0, _mathUtils.addVector)(B, (0, _mathUtils.multiplyVector)(unitY, thickness));
	    var D = pt1;
	    var G = (0, _mathUtils.addVector)(pt0, (0, _mathUtils.multiplyVector)((0, _mathUtils.negateVector)(unitY), thickness * 0.5));
	    var F = (0, _mathUtils.addVector)(G, (0, _mathUtils.multiplyVector)(unitX, (0, _mathUtils.magnitude)(x) - thickness * 2.0));
	    var E = (0, _mathUtils.addVector)(F, (0, _mathUtils.multiplyVector)((0, _mathUtils.negateVector)(unitY), thickness));

	    var points = '' + A.x + ',' + A.y + ' ' + B.x + ',' + B.y + ' ' + C.x + ',' + C.y + ' ' + D.x + ',' + D.y + ' ' + E.x + ',' + E.y + ' ' + F.x + ',' + F.y + ' ' + G.x + ',' + G.y;

	    (0, _setAttributes2.default)(arrow, {
	      points: points,
	      stroke: (0, _normalizeColor2.default)(a.color || '#000'),
	      fill: (0, _normalizeColor2.default)(a.color || '#000')
	    });
	  }

	  return arrow;
	}

/***/ },
/* 94 */
/***/ function(module, exports) {

	"use strict";

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.applyTransform = applyTransform;
	exports.applyInverseTransform = applyInverseTransform;
	exports.transform = transform;
	exports.translate = translate;
	exports.rotate = rotate;
	exports.scale = scale;
	exports.makePoint = makePoint;
	exports.makeVector = makeVector;
	exports.makeVectorFromPoints = makeVectorFromPoints;
	exports.addVector = addVector;
	exports.multiplyVector = multiplyVector;
	exports.magnitude = magnitude;
	exports.negateVector = negateVector;
	exports.unitVector = unitVector;
	exports.crossProduct = crossProduct;
	// Transform point by matrix
	//
	function applyTransform(p, m) {
	  var xt = p[0] * m[0] + p[1] * m[2] + m[4];
	  var yt = p[0] * m[1] + p[1] * m[3] + m[5];
	  return [xt, yt];
	};

	// Transform point by matrix inverse
	//
	function applyInverseTransform(p, m) {
	  var d = m[0] * m[3] - m[1] * m[2];
	  var xt = (p[0] * m[3] - p[1] * m[2] + m[2] * m[5] - m[4] * m[3]) / d;
	  var yt = (-p[0] * m[1] + p[1] * m[0] + m[4] * m[1] - m[5] * m[0]) / d;
	  return [xt, yt];
	};

	// Concatenates two transformation matrices together and returns the result.
	function transform(m1, m2) {
	  return [m1[0] * m2[0] + m1[2] * m2[1], m1[1] * m2[0] + m1[3] * m2[1], m1[0] * m2[2] + m1[2] * m2[3], m1[1] * m2[2] + m1[3] * m2[3], m1[0] * m2[4] + m1[2] * m2[5] + m1[4], m1[1] * m2[4] + m1[3] * m2[5] + m1[5]];
	};

	function translate(m, x, y) {
	  return [m[0], m[1], m[2], m[3], m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]];
	};

	function rotate(m, angle) {
	  angle = angle * Math.PI / 180;

	  var cosValue = Math.cos(angle);
	  var sinValue = Math.sin(angle);

	  return [m[0] * cosValue + m[2] * sinValue, m[1] * cosValue + m[3] * sinValue, m[0] * -sinValue + m[2] * cosValue, m[1] * -sinValue + m[3] * cosValue, m[4], m[5]];
	};

	function scale(m, x, y) {
	  return [m[0] * x, m[1] * x, m[2] * y, m[3] * y, m[4], m[5]];
	};

	function getInverseTransform(m) {
	  var d = m[0] * m[3] - m[1] * m[2];
	  return [m[3] / d, -m[1] / d, -m[2] / d, m[0] / d, (m[2] * m[5] - m[4] * m[3]) / d, (m[4] * m[1] - m[5] * m[0]) / d];
	};

	function makePoint(x, y, z) {
	  return { x: x, y: y, z: z };
	}

	function makeVector(xcoord, ycoord, zcoord) {
	  return { xcoord: xcoord, ycoord: ycoord, zcoord: zcoord };
	}

	function makeVectorFromPoints(pt1, pt2) {
	  var xcoord = pt2.x - pt1.x;
	  var ycoord = pt2.y - pt1.y;
	  var zcoord = pt2.z - pt1.z;
	  return makeVector(xcoord, ycoord, zcoord);
	}

	function addVector(pt, v) {
	  return makePoint(pt.x + v.xcoord, pt.y + v.ycoord, pt.z + v.zcoord);
	}

	function multiplyVector(v, scalar) {
	  return makeVector(v.xcoord * scalar, v.ycoord * scalar, v.zcoord * scalar);
	}

	function magnitude(v) {
	  return Math.sqrt(Math.pow(v.xcoord, 2) + Math.pow(v.ycoord, 2) + Math.pow(v.zcoord, 2));
	}

	function negateVector(v) {
	  return multiplyVector(v, -1);
	}

	function unitVector(v) {
	  var mag = magnitude(v);
	  var xcoord = v.xcoord / mag;
	  var ycoord = v.ycoord / mag;
	  var zcoord = v.zcoord / mag;
	  return makeVector(xcoord, ycoord, zcoord);
	}

	function crossProduct(u, v) {
	  //
	  // u X v = < u2*v3 - u3*v2,
	  //           u3*v1 - u1*v3,
	  //           u1*v2 - u2*v1 >
	  var xcoord = u.ycoord * v.zcoord - u.zcoord * v.ycoord;
	  var ycoord = u.zcoord * v.xcoord - u.xcoord * v.zcoord;
	  var zcoord = u.xcoord * v.ycoord - u.ycoord * v.xcoord;
	  return makeVector(xcoord, ycoord, zcoord);
	}

/***/ },
/* 95 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _uuid = __webpack_require__(96);

	var _uuid2 = _interopRequireDefault(_uuid);

	var _StoreAdapter2 = __webpack_require__(77);

	var _StoreAdapter3 = _interopRequireDefault(_StoreAdapter2);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

	function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

	function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

	// StoreAdapter for working with localStorage
	// This is ideal for testing, examples, and prototyping
	var LocalStoreAdapter = function (_StoreAdapter) {
	  _inherits(LocalStoreAdapter, _StoreAdapter);

	  function LocalStoreAdapter() {
	    _classCallCheck(this, LocalStoreAdapter);

	    return _possibleConstructorReturn(this, (LocalStoreAdapter.__proto__ || Object.getPrototypeOf(LocalStoreAdapter)).call(this, {
	      getAnnotations: function getAnnotations(documentId, pageNumber) {
	        return new Promise(function (resolve, reject) {
	          var annotations = _getAnnotations(documentId).filter(function (i) {
	            return i.page === pageNumber && i.class === 'Annotation';
	          });

	          resolve({
	            documentId: documentId,
	            pageNumber: pageNumber,
	            annotations: annotations
	          });
	        });
	      },
	      getAnnotation: function getAnnotation(documentId, annotationId) {
	        return Promise.resolve(_getAnnotations(documentId)[findAnnotation(documentId, annotationId)]);
	      },
	      addAnnotation: function addAnnotation(documentId, pageNumber, annotation) {
	        return new Promise(function (resolve, reject) {
	          annotation.class = 'Annotation';
	          annotation.uuid = (0, _uuid2.default)();
	          annotation.page = pageNumber;

	          var annotations = _getAnnotations(documentId);
	          annotations.push(annotation);
	          updateAnnotations(documentId, annotations);

	          resolve(annotation);
	        });
	      },
	      editAnnotation: function editAnnotation(documentId, annotationId, annotation) {
	        return new Promise(function (resolve, reject) {
	          var annotations = _getAnnotations(documentId);
	          annotations[findAnnotation(documentId, annotationId)] = annotation;
	          updateAnnotations(documentId, annotations);

	          resolve(annotation);
	        });
	      },
	      deleteAnnotation: function deleteAnnotation(documentId, annotationId) {
	        return new Promise(function (resolve, reject) {
	          var index = findAnnotation(documentId, annotationId);
	          if (index > -1) {
	            var annotations = _getAnnotations(documentId);
	            annotations.splice(index, 1);
	            updateAnnotations(documentId, annotations);
	          }

	          resolve(true);
	        });
	      },
	      getComments: function getComments(documentId, annotationId) {
	        return new Promise(function (resolve, reject) {
	          resolve(_getAnnotations(documentId).filter(function (i) {
	            return i.class === 'Comment' && i.annotation === annotationId;
	          }));
	        });
	      },
	      addComment: function addComment(documentId, annotationId, content) {
	        return new Promise(function (resolve, reject) {
	          var comment = {
	            class: 'Comment',
	            uuid: (0, _uuid2.default)(),
	            annotation: annotationId,
	            content: content
	          };

	          var annotations = _getAnnotations(documentId);
	          annotations.push(comment);
	          updateAnnotations(documentId, annotations);

	          resolve(comment);
	        });
	      },
	      deleteComment: function deleteComment(documentId, commentId) {
	        return new Promise(function (resolve, reject) {
	          _getAnnotations(documentId);
	          var index = -1;
	          var annotations = _getAnnotations(documentId);
	          for (var i = 0, l = annotations.length; i < l; i++) {
	            if (annotations[i].uuid === commentId) {
	              index = i;
	              break;
	            }
	          }

	          if (index > -1) {
	            annotations.splice(index, 1);
	            updateAnnotations(documentId, annotations);
	          }

	          resolve(true);
	        });
	      }
	    }));
	  }

	  return LocalStoreAdapter;
	}(_StoreAdapter3.default);

	exports.default = LocalStoreAdapter;


	function _getAnnotations(documentId) {
	  return JSON.parse(localStorage.getItem(documentId + '/annotations')) || [];
	}

	function updateAnnotations(documentId, annotations) {
	  localStorage.setItem(documentId + '/annotations', JSON.stringify(annotations));
	}

	function findAnnotation(documentId, annotationId) {
	  var index = -1;
	  var annotations = _getAnnotations(documentId);
	  for (var i = 0, l = annotations.length; i < l; i++) {
	    if (annotations[i].uuid === annotationId) {
	      index = i;
	      break;
	    }
	  }
	  return index;
	}

/***/ },
/* 96 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = uuid;
	var REGEXP = /[xy]/g;
	var PATTERN = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';

	function replacement(c) {
	  var r = Math.random() * 16 | 0;
	  var v = c == 'x' ? r : r & 0x3 | 0x8;
	  return v.toString(16);
	}

	/**
	 * Generate a univierally unique identifier
	 *
	 * @return {String}
	 */
	function uuid() {
	  return PATTERN.replace(REGEXP, replacement);
	}

/***/ },
/* 97 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = render;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _appendChild = __webpack_require__(83);

	var _renderScreenReaderHints = __webpack_require__(98);

	var _renderScreenReaderHints2 = _interopRequireDefault(_renderScreenReaderHints);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Render the response from PDFJSAnnotate.getStoreAdapter().getAnnotations to SVG
	 *
	 * @param {SVGElement} svg The SVG element to render the annotations to
	 * @param {Object} viewport The page viewport data
	 * @param {Object} data The response from PDFJSAnnotate.getStoreAdapter().getAnnotations
	 * @return {Promise} Settled once rendering has completed
	 *  A settled Promise will be either:
	 *    - fulfilled: SVGElement
	 *    - rejected: Error
	 */
	function render(svg, viewport, data) {
	  return new Promise(function (resolve, reject) {
	    // Reset the content of the SVG
	    svg.setAttribute('data-pdf-annotate-container', true);
	    svg.setAttribute('data-pdf-annotate-viewport', JSON.stringify(viewport));
	    svg.removeAttribute('data-pdf-annotate-document');
	    svg.removeAttribute('data-pdf-annotate-page');

	    // If there's no data nothing can be done
	    if (!data) {
	      svg.innerHTML = '';
	      return resolve(svg);
	    }

	    svg.setAttribute('data-pdf-annotate-document', data.documentId);
	    svg.setAttribute('data-pdf-annotate-page', data.pageNumber);

	    // Make sure annotations is an array
	    if (!Array.isArray(data.annotations) || data.annotations.length === 0) {
	      return resolve(svg);
	    }

	    // Append or transform annotation to svg
	    data.annotations.forEach(function (a) {
	      var node = svg.querySelector('[data-pdf-annotate-id="' + a.uuid + '"]');
	      if (node) {
	        (0, _appendChild.transformChild)(svg, node, viewport);
	      } else {
	        (0, _appendChild.appendChild)(svg, a, viewport);
	      }
	    });

	    resolve(svg);
	  });
	}

/***/ },
/* 98 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderScreenReaderHints;

	var _insertScreenReaderHint = __webpack_require__(99);

	var _insertScreenReaderHint2 = _interopRequireDefault(_insertScreenReaderHint);

	var _initEventHandlers = __webpack_require__(106);

	var _initEventHandlers2 = _interopRequireDefault(_initEventHandlers);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	// TODO This is not the right place for this to live
	(0, _initEventHandlers2.default)();

	/**
	 * Insert hints into the DOM for screen readers.
	 *
	 * @param {Array} annotations The annotations that hints are inserted for
	 */
	function renderScreenReaderHints(annotations) {
	  annotations = Array.isArray(annotations) ? annotations : [];

	  // Insert hints for each type
	  Object.keys(SORT_TYPES).forEach(function (type) {
	    var sortBy = SORT_TYPES[type];
	    annotations.filter(function (a) {
	      return a.type === type;
	    }).sort(sortBy).forEach(function (a, i) {
	      return (0, _insertScreenReaderHint2.default)(a, i + 1);
	    });
	  });
	}

	// Sort annotations first by y, then by x.
	// This allows hints to be injected in the order they appear,
	// which makes numbering them easier.
	function sortByPoint(a, b) {
	  if (a.y < b.y) {
	    return a.x - b.x;
	  } else {
	    return 1;
	  }
	}

	// Sort annotation by it's first rectangle
	function sortByRectPoint(a, b) {
	  return sortByPoint(a.rectangles[0], b.rectangles[0]);
	}

	// Sort annotation by it's first line
	function sortByLinePoint(a, b) {
	  var lineA = a.lines[0];
	  var lineB = b.lines[0];
	  return sortByPoint({ x: lineA[0], y: lineA[1] }, { x: lineB[0], y: lineB[1] });
	}

	// Arrange supported types and associated sort methods
	var SORT_TYPES = {
	  'highlight': sortByRectPoint,
	  'strikeout': sortByRectPoint,
	  'drawing': sortByLinePoint,
	  'textbox': sortByPoint,
	  'point': sortByPoint,
	  'area': sortByPoint
	};

/***/ },
/* 99 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = insertScreenReaderHint;

	var _createScreenReaderOnly = __webpack_require__(100);

	var _createScreenReaderOnly2 = _interopRequireDefault(_createScreenReaderOnly);

	var _insertElementWithinChildren = __webpack_require__(101);

	var _insertElementWithinChildren2 = _interopRequireDefault(_insertElementWithinChildren);

	var _insertElementWithinElement = __webpack_require__(103);

	var _insertElementWithinElement2 = _interopRequireDefault(_insertElementWithinElement);

	var _renderScreenReaderComments = __webpack_require__(104);

	var _renderScreenReaderComments2 = _interopRequireDefault(_renderScreenReaderComments);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	// Annotation types that support comments
	var COMMENT_TYPES = ['highlight', 'point', 'area', 'circle', 'emptycircle', 'fillcircle'];

	/**
	 * Insert a hint into the DOM for screen readers for a specific annotation.
	 *
	 * @param {Object} annotation The annotation to insert a hint for
	 * @param {Number} num The number of the annotation out of all annotations of the same type
	 */
	function insertScreenReaderHint(annotation) {
	  var num = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 0;

	  switch (annotation.type) {
	    case 'highlight':
	    case 'strikeout':
	      var rects = annotation.rectangles;
	      var first = rects[0];
	      var last = rects[rects.length - 1];

	      (0, _insertElementWithinElement2.default)((0, _createScreenReaderOnly2.default)('Begin ' + annotation.type + ' annotation ' + num, annotation.uuid), first.x, first.y, annotation.page, true);

	      (0, _insertElementWithinElement2.default)((0, _createScreenReaderOnly2.default)('End ' + annotation.type + ' annotation ' + num, annotation.uuid + '-end'), last.x + last.width, last.y, annotation.page, false);
	      break;

	    case 'textbox':
	    case 'point':
	      var text = annotation.type === 'textbox' ? ' (content: ' + annotation.content + ')' : '';

	      (0, _insertElementWithinChildren2.default)((0, _createScreenReaderOnly2.default)(annotation.type + ' annotation ' + num + text, annotation.uuid), annotation.x, annotation.y, annotation.page);
	      break;

	    case 'drawing':
	    case 'area':
	      var x = typeof annotation.x !== 'undefined' ? annotation.x : annotation.lines[0][0];
	      var y = typeof annotation.y !== 'undefined' ? annotation.y : annotation.lines[0][1];

	      (0, _insertElementWithinChildren2.default)((0, _createScreenReaderOnly2.default)('Unlabeled drawing', annotation.uuid), x, y, annotation.page);
	      break;

	    case 'circle':
	    case 'fillcircle':
	    case 'emptycircle':
	      var x2 = typeof annotation.cx !== 'undefined' ? annotation.cx : annotation.lines[0][0];
	      var y2 = typeof annotation.cy !== 'undefined' ? annotation.cy : annotation.lines[0][1];

	      (0, _insertElementWithinChildren2.default)((0, _createScreenReaderOnly2.default)('Unlabeled drawing', annotation.uuid), x2, y2, annotation.page);
	      break;
	  }

	  // Include comments in screen reader hint
	  if (COMMENT_TYPES.includes(annotation.type)) {
	    (0, _renderScreenReaderComments2.default)(annotation.documentId, annotation.uuid);
	  }
	}

/***/ },
/* 100 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = createScreenReaderOnly;
	/**
	 * Create a node that is only visible to screen readers
	 *
	 * @param {String} content The text content that should be read by screen reader
	 * @param {String} [annotationId] The ID of the annotation assocaited
	 * @return {Element} An Element that is only visible to screen readers
	 */
	function createScreenReaderOnly(content, annotationId) {
	  var node = document.createElement('div');
	  var text = document.createTextNode(content);
	  node.appendChild(text);
	  node.setAttribute('id', 'pdf-annotate-screenreader-' + annotationId);
	  node.style.position = 'absolute';
	  node.style.left = '-10000px';
	  node.style.top = 'auto';
	  node.style.width = '1px';
	  node.style.height = '1px';
	  node.style.overflow = 'hidden';
	  return node;
	}

/***/ },
/* 101 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = insertElementWithinChildren;

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _insertElementWithinElement = __webpack_require__(103);

	var _insertElementWithinElement2 = _interopRequireDefault(_insertElementWithinElement);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _toConsumableArray(arr) { if (Array.isArray(arr)) { for (var i = 0, arr2 = Array(arr.length); i < arr.length; i++) { arr2[i] = arr[i]; } return arr2; } else { return Array.from(arr); } }

	/**
	 * Insert an element at a point within the document.
	 * This algorithm will try to insert between elements if possible.
	 * It will however use `insertElementWithinElement` if it is more accurate.
	 *
	 * @param {Element} el The element to be inserted
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 * @param {Number} pageNumber The page number to limit elements to
	 * @return {Boolean} True if element was able to be inserted, otherwise false
	 */
	function insertElementWithinChildren(el, x, y, pageNumber) {
	  // Try and use most accurate method of inserting within an element
	  if ((0, _insertElementWithinElement2.default)(el, x, y, pageNumber, true)) {
	    return true;
	  }

	  // Fall back to inserting between elements
	  var svg = document.querySelector('svg[data-pdf-annotate-page="' + pageNumber + '"]');
	  var rect = svg.getBoundingClientRect();
	  var nodes = [].concat(_toConsumableArray(svg.parentNode.querySelectorAll(_config2.default.textClassQuery() + ' > div')));

	  y = (0, _utils.scaleUp)(svg, { y: y }).y + rect.top;
	  x = (0, _utils.scaleUp)(svg, { x: x }).x + rect.left;

	  // Find the best node to insert before
	  for (var i = 0, l = nodes.length; i < l; i++) {
	    var n = nodes[i];
	    var r = n.getBoundingClientRect();
	    if (y <= r.top) {
	      n.parentNode.insertBefore(el, n);
	      return true;
	    }
	  }

	  // If all else fails try to append to the bottom
	  var textLayer = svg.parentNode.querySelector(_config2.default.textClassQuery());
	  if (textLayer) {
	    var textRect = textLayer.getBoundingClientRect();
	    if ((0, _utils.pointIntersectsRect)(x, y, textRect)) {
	      textLayer.appendChild(el);
	      return true;
	    }
	  }

	  return false;
	}

/***/ },
/* 102 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	    value: true
	});
	exports.default = {
	    annotationLayerName: 'annotationLayer',
	    textLayerName: 'textLayer',
	    annotationSvgQuery: function annotationSvgQuery() {
	        return 'svg.' + this.annotationLayerName;
	    },
	    annotationClassQuery: function annotationClassQuery() {
	        return '.' + this.annotationLayerName;
	    },
	    textClassQuery: function textClassQuery() {
	        return '.' + this.textLayerName;
	    }
	};

/***/ },
/* 103 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = insertElementWithinElement;

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _toConsumableArray(arr) { if (Array.isArray(arr)) { for (var i = 0, arr2 = Array(arr.length); i < arr.length; i++) { arr2[i] = arr[i]; } return arr2; } else { return Array.from(arr); } }

	/**
	 * Insert an element at a point within the document.
	 * This algorithm will only insert within an element amidst it's text content.
	 *
	 * @param {Element} el The element to be inserted
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 * @param {Number} pageNumber The page number to limit elements to
	 * @param {Boolean} insertBefore Whether the element is to be inserted before or after x
	 * @return {Boolean} True if element was able to be inserted, otherwise false
	 */
	function insertElementWithinElement(el, x, y, pageNumber, insertBefore) {
	  var OFFSET_ADJUST = 2;

	  // If inserting before adjust `x` by looking for element a few px to the right
	  // Otherwise adjust a few px to the left
	  // This is to allow a little tolerance by searching within the box, instead
	  // of getting a false negative by testing right on the border.
	  x = Math.max(x + OFFSET_ADJUST * (insertBefore ? 1 : -1), 0);

	  var node = textLayerElementFromPoint(x, y + OFFSET_ADJUST, pageNumber);
	  if (!node) {
	    return false;
	  }

	  // Now that node has been found inverse the adjustment for `x`.
	  // This is done to accomodate tolerance by cutting off on the outside of the
	  // text boundary, instead of missing a character by cutting off within.
	  x = x + OFFSET_ADJUST * (insertBefore ? -1 : 1);

	  var svg = document.querySelector('svg[data-pdf-annotate-page="' + pageNumber + '"]');
	  var left = (0, _utils.scaleDown)(svg, { left: node.getBoundingClientRect().left }).left - svg.getBoundingClientRect().left;
	  var temp = node.cloneNode(true);
	  var head = temp.innerHTML.split('');
	  var tail = [];

	  // Insert temp off screen
	  temp.style.position = 'absolute';
	  temp.style.top = '-10000px';
	  temp.style.left = '-10000px';
	  document.body.appendChild(temp);

	  while (head.length) {
	    // Don't insert within HTML tags
	    if (head[head.length - 1] === '>') {
	      while (head.length) {
	        tail.unshift(head.pop());
	        if (tail[0] === '<') {
	          break;
	        }
	      }
	    }

	    // Check if width of temp based on current head value satisfies x
	    temp.innerHTML = head.join('');
	    var width = (0, _utils.scaleDown)(svg, { width: temp.getBoundingClientRect().width }).width;
	    if (left + width <= x) {
	      break;
	    }
	    tail.unshift(head.pop());
	  }

	  // Update original node with new markup, including element to be inserted
	  node.innerHTML = head.join('') + el.outerHTML + tail.join('');
	  temp.parentNode.removeChild(temp);

	  return true;
	}

	/**
	 * Get a text layer element at a given point on a page
	 *
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 * @param {Number} pageNumber The page to limit elements to
	 * @return {Element} First text layer element found at the point
	 */
	function textLayerElementFromPoint(x, y, pageNumber) {
	  var svg = document.querySelector('svg[data-pdf-annotate-page="' + pageNumber + '"]');
	  var rect = svg.getBoundingClientRect();
	  y = (0, _utils.scaleUp)(svg, { y: y }).y + rect.top;
	  x = (0, _utils.scaleUp)(svg, { x: x }).x + rect.left;
	  return [].concat(_toConsumableArray(svg.parentNode.querySelectorAll(_config2.default.textClassQuery() + ' [data-canvas-width]'))).filter(function (el) {
	    return (0, _utils.pointIntersectsRect)(x, y, el.getBoundingClientRect());
	  })[0];
	}

/***/ },
/* 104 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = renderScreenReaderComments;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _insertScreenReaderComment = __webpack_require__(105);

	var _insertScreenReaderComment2 = _interopRequireDefault(_insertScreenReaderComment);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Insert the comments into the DOM to be available by screen reader
	 *
	 * Example output:
	 *   <div class="screenReaderOnly">
	 *    <div>Begin highlight 1</div>
	 *    <ol aria-label="Comments">
	 *      <li>Foo</li>
	 *      <li>Bar</li>
	 *      <li>Baz</li>
	 *      <li>Qux</li>
	 *    </ol>
	 *  </div>
	 *  <div>Some highlighted text goes here...</div>
	 *  <div class="screenReaderOnly">End highlight 1</div>
	 *
	 * NOTE: `screenReaderOnly` is not a real class, just used for brevity
	 *
	 * @param {String} documentId The ID of the document
	 * @param {String} annotationId The ID of the annotation
	 * @param {Array} [comments] Optionally preloaded comments to be rendered
	 * @return {Promise}
	 */
	function renderScreenReaderComments(documentId, annotationId, comments) {
	  var promise = void 0;

	  if (Array.isArray(comments)) {
	    promise = Promise.resolve(comments);
	  } else {
	    promise = _PDFJSAnnotate2.default.getStoreAdapter().getComments(documentId, annotationId);
	  }

	  return promise.then(function (comments) {
	    // Node needs to be found by querying DOM as it may have been inserted as innerHTML
	    // leaving `screenReaderNode` as an invalid reference (see `insertElementWithinElement`).
	    var node = document.getElementById('pdf-annotate-screenreader-' + annotationId);
	    if (node) {
	      var list = document.createElement('ol');
	      list.setAttribute('id', 'pdf-annotate-screenreader-comment-list-' + annotationId);
	      list.setAttribute('aria-label', 'Comments');
	      node.appendChild(list);
	      comments.forEach(_insertScreenReaderComment2.default);
	    }
	  });
	}

/***/ },
/* 105 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = insertScreenReaderComment;
	/**
	 * Insert a comment into the DOM to be available by screen reader
	 *
	 * @param {Object} comment The comment to be inserted
	 */
	function insertScreenReaderComment(comment) {
	  if (!comment) {
	    return;
	  }

	  var list = document.querySelector('#pdf-annotate-screenreader-' + comment.annotation + ' ol');
	  if (list) {
	    var item = document.createElement('li');
	    item.setAttribute('id', 'pdf-annotate-screenreader-comment-' + comment.uuid);
	    item.appendChild(document.createTextNode('' + comment.content));
	    list.appendChild(item);
	  }
	}

/***/ },
/* 106 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = initEventHandlers;

	var _insertScreenReaderHint = __webpack_require__(99);

	var _insertScreenReaderHint2 = _interopRequireDefault(_insertScreenReaderHint);

	var _renderScreenReaderHints = __webpack_require__(98);

	var _renderScreenReaderHints2 = _interopRequireDefault(_renderScreenReaderHints);

	var _insertScreenReaderComment = __webpack_require__(105);

	var _insertScreenReaderComment2 = _interopRequireDefault(_insertScreenReaderComment);

	var _renderScreenReaderComments = __webpack_require__(104);

	var _renderScreenReaderComments2 = _interopRequireDefault(_renderScreenReaderComments);

	var _event = __webpack_require__(79);

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	/**
	 * Initialize the event handlers for keeping screen reader hints synced with data
	 */
	function initEventHandlers() {
	  (0, _event.addEventListener)('annotation:add', function (documentId, pageNumber, annotation) {
	    reorderAnnotationsByType(documentId, pageNumber, annotation.type);
	  });
	  (0, _event.addEventListener)('annotation:edit', function (documentId, annotationId, annotation) {
	    reorderAnnotationsByType(documentId, annotation.page, annotation.type);
	  });
	  (0, _event.addEventListener)('annotation:delete', removeAnnotation);
	  (0, _event.addEventListener)('comment:add', insertComment);
	  (0, _event.addEventListener)('comment:delete', removeComment);
	}

	/**
	 * Reorder the annotation numbers by annotation type
	 *
	 * @param {String} documentId The ID of the document
	 * @param {Number} pageNumber The page number of the annotations
	 * @param {Strig} type The annotation type
	 */
	function reorderAnnotationsByType(documentId, pageNumber, type) {
	  _PDFJSAnnotate2.default.getStoreAdapter().getAnnotations(documentId, pageNumber).then(function (annotations) {
	    return annotations.annotations.filter(function (a) {
	      return a.type === type;
	    });
	  }).then(function (annotations) {
	    annotations.forEach(function (a) {
	      removeAnnotation(documentId, a.uuid);
	    });

	    return annotations;
	  }).then(_renderScreenReaderHints2.default);
	}

	/**
	 * Remove the screen reader hint for an annotation
	 *
	 * @param {String} documentId The ID of the document
	 * @param {String} annotationId The Id of the annotation
	 */
	function removeAnnotation(documentId, annotationId) {
	  removeElementById('pdf-annotate-screenreader-' + annotationId);
	  removeElementById('pdf-annotate-screenreader-' + annotationId + '-end');
	}

	/**
	 * Insert a screen reader hint for a comment
	 *
	 * @param {String} documentId The ID of the document
	 * @param {String} annotationId The ID of tha assocated annotation
	 * @param {Object} comment The comment to insert a hint for
	 */
	function insertComment(documentId, annotationId, comment) {
	  var list = document.querySelector('pdf-annotate-screenreader-comment-list-' + annotationId);
	  var promise = void 0;

	  if (!list) {
	    promise = (0, _renderScreenReaderComments2.default)(documentId, annotationId, []).then(function () {
	      list = document.querySelector('pdf-annotate-screenreader-comment-list-' + annotationId);
	      return true;
	    });
	  } else {
	    promise = Promise.resolve(true);
	  }

	  promise.then(function () {
	    (0, _insertScreenReaderComment2.default)(comment);
	  });
	}

	/**
	 * Remove a screen reader hint for a comment
	 *
	 * @param {String} documentId The ID of the document
	 * @param {String} commentId The ID of the comment
	 */
	function removeComment(documentId, commentId) {
	  removeElementById('pdf-annotate-screenreader-comment-' + commentId);
	}

	/**
	 * Remove an element from the DOM by it's ID if it exists
	 *
	 * @param {String} elementID The ID of the element to be removed
	 */
	function removeElementById(elementId) {
	  var el = document.getElementById(elementId);
	  if (el) {
	    el.parentNode.removeChild(el);
	  }
	}

/***/ },
/* 107 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _event = __webpack_require__(79);

	var _edit = __webpack_require__(108);

	var _pen = __webpack_require__(109);

	var _arrow = __webpack_require__(110);

	var _point = __webpack_require__(111);

	var _rect = __webpack_require__(112);

	var _circle = __webpack_require__(113);

	var _text = __webpack_require__(114);

	var _page = __webpack_require__(115);

	exports.default = {
	  addEventListener: _event.addEventListener, removeEventListener: _event.removeEventListener, fireEvent: _event.fireEvent,
	  disableEdit: _edit.disableEdit, enableEdit: _edit.enableEdit,
	  disablePen: _pen.disablePen, enablePen: _pen.enablePen, setPen: _pen.setPen,
	  disablePoint: _point.disablePoint, enablePoint: _point.enablePoint,
	  disableRect: _rect.disableRect, enableRect: _rect.enableRect,
	  disableCircle: _circle.disableCircle, enableCircle: _circle.enableCircle, setCircle: _circle.setCircle, addCircle: _circle.addCircle,
	  disableArrow: _arrow.disableArrow, enableArrow: _arrow.enableArrow, setArrow: _arrow.setArrow,
	  disableText: _text.disableText, enableText: _text.enableText, setText: _text.setText,
	  createPage: _page.createPage, renderPage: _page.renderPage
	};

/***/ },
/* 108 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _slicedToArray = function () { function sliceIterator(arr, i) { var _arr = []; var _n = true; var _d = false; var _e = undefined; try { for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"]) _i["return"](); } finally { if (_d) throw _e; } } return _arr; } return function (arr, i) { if (Array.isArray(arr)) { return arr; } else if (Symbol.iterator in Object(arr)) { return sliceIterator(arr, i); } else { throw new TypeError("Invalid attempt to destructure non-iterable instance"); } }; }();

	exports.enableEdit = enableEdit;
	exports.disableEdit = disableEdit;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _appendChild = __webpack_require__(83);

	var _event = __webpack_require__(79);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _toConsumableArray(arr) { if (Array.isArray(arr)) { for (var i = 0, arr2 = Array(arr.length); i < arr.length; i++) { arr2[i] = arr[i]; } return arr2; } else { return Array.from(arr); } }

	var _enabled = false;
	var isDragging = false,
	    overlay = void 0;
	var dragOffsetX = void 0,
	    dragOffsetY = void 0,
	    dragStartX = void 0,
	    dragStartY = void 0;
	var OVERLAY_BORDER_SIZE = 3;

	/**
	 * Create an overlay for editing an annotation.
	 *
	 * @param {Element} target The annotation element to apply overlay for
	 */
	function createEditOverlay(target) {
	  destroyEditOverlay();

	  overlay = document.createElement('div');
	  var anchor = document.createElement('a');
	  var parentNode = (0, _utils.findSVGContainer)(target).parentNode;
	  var id = target.getAttribute('data-pdf-annotate-id');
	  var rect = (0, _utils.getOffsetAnnotationRect)(target);
	  var styleLeft = rect.left - OVERLAY_BORDER_SIZE;
	  var styleTop = rect.top - OVERLAY_BORDER_SIZE;

	  overlay.setAttribute('id', 'pdf-annotate-edit-overlay');
	  overlay.setAttribute('data-target-id', id);
	  overlay.style.boxSizing = 'content-box';
	  overlay.style.position = 'absolute';
	  overlay.style.top = styleTop + 'px';
	  overlay.style.left = styleLeft + 'px';
	  overlay.style.width = rect.width + 'px';
	  overlay.style.height = rect.height + 'px';
	  overlay.style.border = OVERLAY_BORDER_SIZE + 'px solid ' + _utils.BORDER_COLOR;
	  overlay.style.borderRadius = OVERLAY_BORDER_SIZE + 'px';
	  overlay.style.zIndex = 20100;

	  anchor.innerHTML = '×';
	  anchor.setAttribute('href', 'javascript://');
	  anchor.style.background = '#fff';
	  anchor.style.borderRadius = '20px';
	  anchor.style.border = '1px solid #bbb';
	  anchor.style.color = '#bbb';
	  anchor.style.fontSize = '16px';
	  anchor.style.padding = '2px';
	  anchor.style.textAlign = 'center';
	  anchor.style.textDecoration = 'none';
	  anchor.style.position = 'absolute';
	  anchor.style.top = '-13px';
	  anchor.style.right = '-13px';
	  anchor.style.width = '25px';
	  anchor.style.height = '25px';

	  overlay.appendChild(anchor);
	  parentNode.appendChild(overlay);
	  document.addEventListener('click', handleDocumentClick);
	  document.addEventListener('keyup', handleDocumentKeyup);
	  document.addEventListener('mousedown', handleDocumentMousedown);
	  anchor.addEventListener('click', deleteAnnotation);
	  anchor.addEventListener('mouseover', function () {
	    anchor.style.color = '#35A4DC';
	    anchor.style.borderColor = '#999';
	    anchor.style.boxShadow = '0 1px 1px #ccc';
	  });
	  anchor.addEventListener('mouseout', function () {
	    anchor.style.color = '#bbb';
	    anchor.style.borderColor = '#bbb';
	    anchor.style.boxShadow = '';
	  });
	  overlay.addEventListener('mouseover', function () {
	    if (!isDragging) {
	      anchor.style.display = '';
	    }
	  });
	  overlay.addEventListener('mouseout', function () {
	    anchor.style.display = 'none';
	  });
	}

	/**
	 * Destroy the edit overlay if it exists.
	 */
	function destroyEditOverlay() {
	  if (overlay) {
	    overlay.parentNode.removeChild(overlay);
	    overlay = null;
	  }

	  document.removeEventListener('click', handleDocumentClick);
	  document.removeEventListener('keyup', handleDocumentKeyup);
	  document.removeEventListener('mousedown', handleDocumentMousedown);
	  document.removeEventListener('mousemove', handleDocumentMousemove);
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	  (0, _utils.enableUserSelect)();
	}

	/**
	 * Delete currently selected annotation
	 */
	function deleteAnnotation() {
	  if (!overlay) {
	    return;
	  }

	  var annotationId = overlay.getAttribute('data-target-id');
	  var nodes = document.querySelectorAll('[data-pdf-annotate-id="' + annotationId + '"]');
	  var svg = overlay.parentNode.querySelector(_config2.default.annotationSvgQuery());

	  var _getMetadata = (0, _utils.getMetadata)(svg),
	      documentId = _getMetadata.documentId;

	  [].concat(_toConsumableArray(nodes)).forEach(function (n) {
	    n.parentNode.removeChild(n);
	  });

	  _PDFJSAnnotate2.default.getStoreAdapter().deleteAnnotation(documentId, annotationId);

	  destroyEditOverlay();
	}

	/**
	 * Handle document.click event
	 *
	 * @param {Event} e The DOM event that needs to be handled
	 */
	function handleDocumentClick(e) {
	  if (!(0, _utils.findSVGAtPoint)(e.clientX, e.clientY)) {
	    return;
	  }

	  // Remove current overlay
	  var overlay = document.getElementById('pdf-annotate-edit-overlay');
	  if (overlay) {
	    if (isDragging || e.target === overlay) {
	      return;
	    }

	    destroyEditOverlay();
	  }
	}

	/**
	 * Handle document.keyup event
	 *
	 * @param {Event} e The DOM event that needs to be handled
	 */
	function handleDocumentKeyup(e) {
	  if (overlay && e.keyCode === 46 && e.target.nodeName.toLowerCase() !== 'textarea' && e.target.nodeName.toLowerCase() !== 'input') {
	    deleteAnnotation();
	  }
	}

	/**
	 * Handle document.mousedown event
	 *
	 * @param {Event} e The DOM event that needs to be handled
	 */
	function handleDocumentMousedown(e) {
	  if (e.target !== overlay) {
	    return;
	  }

	  // Highlight and strikeout annotations are bound to text within the document.
	  // It doesn't make sense to allow repositioning these types of annotations.
	  var annotationId = overlay.getAttribute('data-target-id');
	  var target = document.querySelector('[data-pdf-annotate-id="' + annotationId + '"]');
	  var type = target.getAttribute('data-pdf-annotate-type');

	  if (type === 'highlight' || type === 'strikeout') {
	    return;
	  }

	  isDragging = true;
	  dragOffsetX = e.clientX;
	  dragOffsetY = e.clientY;
	  dragStartX = overlay.offsetLeft;
	  dragStartY = overlay.offsetTop;

	  overlay.style.background = 'rgba(255, 255, 255, 0.7)';
	  overlay.style.cursor = 'move';
	  overlay.querySelector('a').style.display = 'none';

	  document.addEventListener('mousemove', handleDocumentMousemove);
	  document.addEventListener('mouseup', handleDocumentMouseup);
	  (0, _utils.disableUserSelect)();
	}

	/**
	 * Handle document.mousemove event
	 *
	 * @param {Event} e The DOM event that needs to be handled
	 */
	function handleDocumentMousemove(e) {
	  var annotationId = overlay.getAttribute('data-target-id');
	  var parentNode = overlay.parentNode;
	  var rect = parentNode.getBoundingClientRect();
	  var y = dragStartY + (e.clientY - dragOffsetY);
	  var x = dragStartX + (e.clientX - dragOffsetX);
	  var minY = 0;
	  var maxY = rect.height;
	  var minX = 0;
	  var maxX = rect.width;

	  if (y > minY && y + overlay.offsetHeight < maxY) {
	    overlay.style.top = y + 'px';
	  }

	  if (x > minX && x + overlay.offsetWidth < maxX) {
	    overlay.style.left = x + 'px';
	  }
	}

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} e The DOM event that needs to be handled
	 */
	function handleDocumentMouseup(e) {
	  var annotationId = overlay.getAttribute('data-target-id');
	  var target = document.querySelectorAll('[data-pdf-annotate-id="' + annotationId + '"]');
	  var type = target[0].getAttribute('data-pdf-annotate-type');
	  var svg = overlay.parentNode.querySelector(_config2.default.annotationSvgQuery());

	  var _getMetadata2 = (0, _utils.getMetadata)(svg),
	      documentId = _getMetadata2.documentId;

	  overlay.querySelector('a').style.display = '';

	  _PDFJSAnnotate2.default.getStoreAdapter().getAnnotation(documentId, annotationId).then(function (annotation) {
	    var attribX = 'x';
	    var attribY = 'y';
	    if (['circle', 'fillcircle', 'emptycircle'].indexOf(type) > -1) {
	      attribX = 'cx';
	      attribY = 'cy';
	    }
	    if (['area', 'highlight', 'point', 'textbox', 'circle', 'fillcircle', 'emptycircle'].indexOf(type) > -1) {
	      var modelStart = (0, _utils.convertToSvgPoint)([dragStartX, dragStartY], svg);
	      var modelEnd = (0, _utils.convertToSvgPoint)([overlay.offsetLeft, overlay.offsetTop], svg);
	      var modelDelta = {
	        x: modelEnd[0] - modelStart[0],
	        y: modelEnd[1] - modelStart[1]
	      };

	      if (type === 'textbox') {
	        target = [target[0].firstChild];
	      }

	      [].concat(_toConsumableArray(target)).forEach(function (t, i) {
	        var modelX = parseInt(t.getAttribute(attribX), 10);
	        var modelY = parseInt(t.getAttribute(attribY), 10);
	        if (modelDelta.y !== 0) {
	          modelY = modelY + modelDelta.y;
	          var viewY = modelY;

	          if (type === 'point') {
	            viewY = (0, _utils.scaleUp)(svg, { viewY: viewY }).viewY;
	          }

	          t.setAttribute(attribY, viewY);
	          if (annotation.rectangles && i < annotation.rectangles.length) {
	            annotation.rectangles[i].y = modelY;
	          } else if (annotation[attribY]) {
	            annotation[attribY] = modelY;
	          }
	        }
	        if (modelDelta.x !== 0) {
	          modelX = modelX + modelDelta.x;
	          var viewX = modelX;

	          if (type === 'point') {
	            viewX = (0, _utils.scaleUp)(svg, { viewX: viewX }).viewX;
	          }

	          t.setAttribute(attribX, viewX);
	          if (annotation.rectangles && i < annotation.rectangles.length) {
	            annotation.rectangles[i].x = modelX;
	          } else if (annotation[attribX]) {
	            annotation[attribX] = modelX;
	          }
	        }
	      });
	      // } else if (type === 'strikeout') {
	      //   let { deltaX, deltaY } = getDelta('x1', 'y1');
	      //   [...target].forEach(target, (t, i) => {
	      //     if (deltaY !== 0) {
	      //       t.setAttribute('y1', parseInt(t.getAttribute('y1'), 10) + deltaY);
	      //       t.setAttribute('y2', parseInt(t.getAttribute('y2'), 10) + deltaY);
	      //       annotation.rectangles[i].y = parseInt(t.getAttribute('y1'), 10);
	      //     }
	      //     if (deltaX !== 0) {
	      //       t.setAttribute('x1', parseInt(t.getAttribute('x1'), 10) + deltaX);
	      //       t.setAttribute('x2', parseInt(t.getAttribute('x2'), 10) + deltaX);
	      //       annotation.rectangles[i].x = parseInt(t.getAttribute('x1'), 10);
	      //     }
	      //   });
	    } else if (type === 'drawing' || type === 'arrow') {
	      var _modelStart = (0, _utils.convertToSvgPoint)([dragStartX, dragStartY], svg);
	      var _modelEnd = (0, _utils.convertToSvgPoint)([overlay.offsetLeft, overlay.offsetTop], svg);
	      var _modelDelta = {
	        x: _modelEnd[0] - _modelStart[0],
	        y: _modelEnd[1] - _modelStart[1]
	      };

	      annotation.lines.forEach(function (line, i) {
	        var _annotation$lines$i = _slicedToArray(annotation.lines[i], 2),
	            x = _annotation$lines$i[0],
	            y = _annotation$lines$i[1];

	        annotation.lines[i][0] = x + _modelDelta.x;
	        annotation.lines[i][1] = y + _modelDelta.y;
	      });

	      target[0].parentNode.removeChild(target[0]);
	      (0, _appendChild.appendChild)(svg, annotation);
	    }

	    _PDFJSAnnotate2.default.getStoreAdapter().editAnnotation(documentId, annotationId, annotation);
	  });

	  setTimeout(function () {
	    isDragging = false;
	  }, 0);

	  overlay.style.background = '';
	  overlay.style.cursor = '';

	  document.removeEventListener('mousemove', handleDocumentMousemove);
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	  (0, _utils.enableUserSelect)();
	}

	/**
	 * Handle annotation.click event
	 *
	 * @param {Element} e The annotation element that was clicked
	 */
	function handleAnnotationClick(target) {
	  createEditOverlay(target);
	}

	/**
	 * Enable edit mode behavior.
	 */
	function enableEdit() {
	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  (0, _event.addEventListener)('annotation:click', handleAnnotationClick);
	};

	/**
	 * Disable edit mode behavior.
	 */
	function disableEdit() {
	  destroyEditOverlay();

	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  (0, _event.removeEventListener)('annotation:click', handleAnnotationClick);
	};

/***/ },
/* 109 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.setPen = setPen;
	exports.enablePen = enablePen;
	exports.disablePen = disablePen;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _appendChild = __webpack_require__(83);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var _enabled = false;
	var _penSize = void 0;
	var _penColor = void 0;
	var path = void 0;
	var lines = void 0;

	/**
	 * Handle document.mousedown event
	 */
	function handleDocumentMousedown() {
	  path = null;
	  lines = [];

	  document.addEventListener('mousemove', handleDocumentMousemove);
	  document.addEventListener('mouseup', handleDocumentMouseup);
	}

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	function handleDocumentMouseup(e) {
	  var svg = void 0;
	  if (lines.length > 1 && (svg = (0, _utils.findSVGAtPoint)(e.clientX, e.clientY))) {
	    var _getMetadata = (0, _utils.getMetadata)(svg),
	        documentId = _getMetadata.documentId,
	        pageNumber = _getMetadata.pageNumber;

	    _PDFJSAnnotate2.default.getStoreAdapter().addAnnotation(documentId, pageNumber, {
	      type: 'drawing',
	      width: _penSize,
	      color: _penColor,
	      lines: lines
	    }).then(function (annotation) {
	      if (path) {
	        svg.removeChild(path);
	      }

	      (0, _appendChild.appendChild)(svg, annotation);
	    });
	  }

	  document.removeEventListener('mousemove', handleDocumentMousemove);
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	}

	/**
	 * Handle document.mousemove event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	function handleDocumentMousemove(e) {
	  savePoint(e.clientX, e.clientY);
	}

	/**
	 * Handle document.keyup event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	function handleDocumentKeyup(e) {
	  // Cancel rect if Esc is pressed
	  if (e.keyCode === 27) {
	    lines = null;
	    path.parentNode.removeChild(path);
	    document.removeEventListener('mousemove', handleDocumentMousemove);
	    document.removeEventListener('mouseup', handleDocumentMouseup);
	  }
	}

	/**
	 * Save a point to the line being drawn.
	 *
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 */
	function savePoint(x, y) {
	  var svg = (0, _utils.findSVGAtPoint)(x, y);
	  if (!svg) {
	    return;
	  }

	  var rect = svg.getBoundingClientRect();
	  var point = (0, _utils.convertToSvgPoint)([x - rect.left, y - rect.top], svg);

	  lines.push(point);

	  if (lines.length <= 1) {
	    return;
	  }

	  if (path) {
	    svg.removeChild(path);
	  }

	  path = (0, _appendChild.appendChild)(svg, {
	    type: 'drawing',
	    color: _penColor,
	    width: _penSize,
	    lines: lines
	  });
	}

	/**
	 * Set the attributes of the pen.
	 *
	 * @param {Number} penSize The size of the lines drawn by the pen
	 * @param {String} penColor The color of the lines drawn by the pen
	 */
	function setPen() {
	  var penSize = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 1;
	  var penColor = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '000000';

	  _penSize = parseInt(penSize, 10);
	  _penColor = penColor;
	}

	/**
	 * Enable the pen behavior
	 */
	function enablePen() {
	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  document.addEventListener('mousedown', handleDocumentMousedown);
	  document.addEventListener('keyup', handleDocumentKeyup);
	  (0, _utils.disableUserSelect)();
	}

	/**
	 * Disable the pen behavior
	 */
	function disablePen() {
	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  document.removeEventListener('mousedown', handleDocumentMousedown);
	  document.removeEventListener('keyup', handleDocumentKeyup);
	  (0, _utils.enableUserSelect)();
	}

/***/ },
/* 110 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.setArrow = setArrow;
	exports.enableArrow = enableArrow;
	exports.disableArrow = disableArrow;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _appendChild = __webpack_require__(83);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var _enabled = false;
	var _penSize = void 0;
	var _penColor = void 0;
	var path = void 0;
	var lines = void 0;
	var originY = void 0;
	var originX = void 0;

	/**
	 * Handle document.mousedown event
	 */
	function handleDocumentMousedown(e) {
	  var target = (0, _utils.findAnnotationAtPoint)(e.clientX, e.clientY);
	  if (target === null) return;

	  var type = target.getAttribute('data-pdf-annotate-type');
	  if (type !== 'circle' && type !== 'fillcircle' && type !== 'emptycircle') {
	    return;
	  }

	  var svg = (0, _utils.findSVGContainer)(target);

	  var _getMetadata = (0, _utils.getMetadata)(svg),
	      documentId = _getMetadata.documentId;

	  var annotationId = target.getAttribute('data-pdf-annotate-id');

	  var event = e;
	  _PDFJSAnnotate2.default.getStoreAdapter().getAnnotation(documentId, annotationId).then(function (annotation) {
	    if (annotation) {
	      path = null;
	      lines = [];

	      var point = (0, _utils.convertToScreenPoint)([annotation.cx, annotation.cy], svg);

	      var rect = svg.getBoundingClientRect();

	      originX = point[0] + rect.left;
	      originY = point[1] + rect.top;

	      document.addEventListener('mousemove', handleDocumentMousemove);
	      document.addEventListener('mouseup', handleDocumentMouseup);
	    }
	  });
	}

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	function handleDocumentMouseup(e) {
	  var svg = void 0;
	  if (lines.length > 1 && (svg = (0, _utils.findSVGAtPoint)(e.clientX, e.clientY))) {
	    var _getMetadata2 = (0, _utils.getMetadata)(svg),
	        documentId = _getMetadata2.documentId,
	        pageNumber = _getMetadata2.pageNumber;

	    _PDFJSAnnotate2.default.getStoreAdapter().addAnnotation(documentId, pageNumber, {
	      type: 'arrow',
	      width: _penSize,
	      color: _penColor,
	      lines: lines
	    }).then(function (annotation) {
	      if (path) {
	        svg.removeChild(path);
	      }

	      (0, _appendChild.appendChild)(svg, annotation);
	    });
	  }

	  document.removeEventListener('mousemove', handleDocumentMousemove);
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	}

	/**
	 * Handle document.mousemove event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	function handleDocumentMousemove(e) {
	  var x = lines.length === 0 ? originX : e.clientX;
	  var y = lines.length === 0 ? originY : e.clientY;

	  savePoint(x, y);
	}

	/**
	 * Handle document.keyup event
	 *
	 * @param {Event} e The DOM event to be handled
	 */
	function handleDocumentKeyup(e) {
	  // Cancel rect if Esc is pressed
	  if (e.keyCode === 27) {
	    lines = null;
	    path.parentNode.removeChild(path);
	    document.removeEventListener('mousemove', handleDocumentMousemove);
	    document.removeEventListener('mouseup', handleDocumentMouseup);
	  }
	}

	/**
	 * Save a point to the line being drawn.
	 *
	 * @param {Number} x The x coordinate of the point
	 * @param {Number} y The y coordinate of the point
	 */
	function savePoint(x, y) {
	  var svg = (0, _utils.findSVGAtPoint)(x, y);
	  if (!svg) {
	    return;
	  }

	  var rect = svg.getBoundingClientRect();
	  var point = (0, _utils.convertToSvgPoint)([x - rect.left, y - rect.top], svg);

	  if (lines.length < 2) {
	    lines.push(point);
	    return;
	  } else {
	    lines[1] = point; // update end point
	  }

	  if (path) {
	    svg.removeChild(path);
	  }

	  path = (0, _appendChild.appendChild)(svg, {
	    type: 'arrow',
	    color: _penColor,
	    width: _penSize,
	    lines: lines
	  });
	}

	/**
	 * Set the attributes of the pen.
	 *
	 * @param {Number} penSize The size of the lines drawn by the pen
	 * @param {String} penColor The color of the lines drawn by the pen
	 */
	function setArrow() {
	  var penSize = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 10;
	  var penColor = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '0000FF';

	  _penSize = parseInt(penSize, 10);
	  _penColor = penColor;
	}

	/**
	 * Enable the pen behavior
	 */
	function enableArrow() {
	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  document.addEventListener('mousedown', handleDocumentMousedown);
	  document.addEventListener('keyup', handleDocumentKeyup);
	  (0, _utils.disableUserSelect)();
	}

	/**
	 * Disable the pen behavior
	 */
	function disableArrow() {
	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  document.removeEventListener('mousedown', handleDocumentMousedown);
	  document.removeEventListener('keyup', handleDocumentKeyup);
	  (0, _utils.enableUserSelect)();
	}

/***/ },
/* 111 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.enablePoint = enablePoint;
	exports.disablePoint = disablePoint;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _appendChild = __webpack_require__(83);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var _enabled = false;
	var input = void 0;

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} The DOM event to be handled
	 */
	function handleDocumentMouseup(e) {
	  if (input || !(0, _utils.findSVGAtPoint)(e.clientX, e.clientY)) {
	    return;
	  }

	  input = document.createElement('input');
	  input.setAttribute('id', 'pdf-annotate-point-input');
	  input.setAttribute('placeholder', 'Enter comment');
	  input.style.border = '3px solid ' + _utils.BORDER_COLOR;
	  input.style.borderRadius = '3px';
	  input.style.position = 'absolute';
	  input.style.top = e.clientY + 'px';
	  input.style.left = e.clientX + 'px';

	  input.addEventListener('blur', handleInputBlur);
	  input.addEventListener('keyup', handleInputKeyup);

	  document.body.appendChild(input);
	  input.focus();
	}

	/**
	 * Handle input.blur event
	 */
	function handleInputBlur() {
	  savePoint();
	}

	/**
	 * Handle input.keyup event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleInputKeyup(e) {
	  if (e.keyCode === 27) {
	    closeInput();
	  } else if (e.keyCode === 13) {
	    savePoint();
	  }
	}

	/**
	 * Save a new point annotation from input
	 */
	function savePoint() {
	  if (input.value.trim().length > 0) {
	    var clientX = parseInt(input.style.left, 10);
	    var clientY = parseInt(input.style.top, 10);
	    var content = input.value.trim();
	    var svg = (0, _utils.findSVGAtPoint)(clientX, clientY);
	    if (!svg) {
	      return;
	    }

	    var rect = svg.getBoundingClientRect();

	    var _getMetadata = (0, _utils.getMetadata)(svg),
	        documentId = _getMetadata.documentId,
	        pageNumber = _getMetadata.pageNumber;

	    var annotation = Object.assign({
	      type: 'point'
	    }, (0, _utils.scaleDown)(svg, {
	      x: clientX - rect.left,
	      y: clientY - rect.top
	    }));

	    _PDFJSAnnotate2.default.getStoreAdapter().addAnnotation(documentId, pageNumber, annotation).then(function (annotation) {
	      _PDFJSAnnotate2.default.getStoreAdapter().addComment(documentId, annotation.uuid, content);

	      (0, _appendChild.appendChild)(svg, annotation);
	    });
	  }

	  closeInput();
	}

	/**
	 * Close the input element
	 */
	function closeInput() {
	  input.removeEventListener('blur', handleInputBlur);
	  input.removeEventListener('keyup', handleInputKeyup);
	  document.body.removeChild(input);
	  input = null;
	}

	/**
	 * Enable point annotation behavior
	 */
	function enablePoint() {
	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  document.addEventListener('mouseup', handleDocumentMouseup);
	}

	/**
	 * Disable point annotation behavior
	 */
	function disablePoint() {
	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	}

/***/ },
/* 112 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.enableRect = enableRect;
	exports.disableRect = disableRect;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _appendChild = __webpack_require__(83);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	function _toConsumableArray(arr) { if (Array.isArray(arr)) { for (var i = 0, arr2 = Array(arr.length); i < arr.length; i++) { arr2[i] = arr[i]; } return arr2; } else { return Array.from(arr); } }

	var _enabled = false;
	var _type = void 0;
	var overlay = void 0;
	var originY = void 0;
	var originX = void 0;

	/**
	 * Get the current window selection as rects
	 *
	 * @return {Array} An Array of rects
	 */
	function getSelectionRects() {
	  try {
	    var selection = window.getSelection();
	    var range = selection.getRangeAt(0);
	    var rects = range.getClientRects();

	    if (rects.length > 0 && rects[0].width > 0 && rects[0].height > 0) {
	      return rects;
	    }
	  } catch (e) {}

	  return null;
	}

	/**
	 * Handle document.mousedown event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleDocumentMousedown(e) {
	  var svg = void 0;
	  if (_type !== 'area' || !(svg = (0, _utils.findSVGAtPoint)(e.clientX, e.clientY))) {
	    return;
	  }

	  var rect = svg.getBoundingClientRect();
	  originY = e.clientY;
	  originX = e.clientX;

	  overlay = document.createElement('div');
	  overlay.style.position = 'absolute';
	  overlay.style.top = originY - rect.top + 'px';
	  overlay.style.left = originX - rect.left + 'px';
	  overlay.style.border = '3px solid ' + _utils.BORDER_COLOR;
	  overlay.style.borderRadius = '3px';
	  svg.parentNode.appendChild(overlay);

	  document.addEventListener('mousemove', handleDocumentMousemove);
	  (0, _utils.disableUserSelect)();
	}

	/**
	 * Handle document.mousemove event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleDocumentMousemove(e) {
	  var svg = overlay.parentNode.querySelector(_config2.default.annotationSvgQuery());
	  var rect = svg.getBoundingClientRect();

	  if (originX + (e.clientX - originX) < rect.right) {
	    overlay.style.width = e.clientX - originX + 'px';
	  }

	  if (originY + (e.clientY - originY) < rect.bottom) {
	    overlay.style.height = e.clientY - originY + 'px';
	  }
	}

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleDocumentMouseup(e) {
	  var rects = void 0;
	  if (_type !== 'area' && (rects = getSelectionRects())) {
	    var svg = (0, _utils.findSVGAtPoint)(rects[0].left, rects[0].top);
	    saveRect(_type, [].concat(_toConsumableArray(rects)).map(function (r) {
	      return {
	        top: r.top,
	        left: r.left,
	        width: r.width,
	        height: r.height
	      };
	    }));
	  } else if (_type === 'area' && overlay) {
	    var _svg = overlay.parentNode.querySelector(_config2.default.annotationSvgQuery());
	    var rect = _svg.getBoundingClientRect();
	    saveRect(_type, [{
	      top: parseInt(overlay.style.top, 10) + rect.top,
	      left: parseInt(overlay.style.left, 10) + rect.left,
	      width: parseInt(overlay.style.width, 10),
	      height: parseInt(overlay.style.height, 10)
	    }]);

	    overlay.parentNode.removeChild(overlay);
	    overlay = null;

	    document.removeEventListener('mousemove', handleDocumentMousemove);
	    (0, _utils.enableUserSelect)();
	  }
	}

	/**
	 * Handle document.keyup event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleDocumentKeyup(e) {
	  // Cancel rect if Esc is pressed
	  if (e.keyCode === 27) {
	    var selection = window.getSelection();
	    selection.removeAllRanges();
	    if (overlay && overlay.parentNode) {
	      overlay.parentNode.removeChild(overlay);
	      overlay = null;
	      document.removeEventListener('mousemove', handleDocumentMousemove);
	    }
	  }
	}

	/**
	 * Save a rect annotation
	 *
	 * @param {String} type The type of rect (area, highlight, strikeout)
	 * @param {Array} rects The rects to use for annotation
	 * @param {String} color The color of the rects
	 */
	function saveRect(type, rects, color) {
	  var svg = (0, _utils.findSVGAtPoint)(rects[0].left, rects[0].top);
	  var annotation = void 0;

	  if (!svg) {
	    return;
	  }

	  var boundingRect = svg.getBoundingClientRect();

	  if (!color) {
	    if (type === 'highlight') {
	      color = 'FFFF00';
	    } else if (type === 'strikeout') {
	      color = 'FF0000';
	    }
	  }

	  // Initialize the annotation
	  annotation = {
	    type: type,
	    color: color,
	    rectangles: [].concat(_toConsumableArray(rects)).map(function (r) {
	      var offset = 0;

	      if (type === 'strikeout') {
	        offset = r.height / 2;
	      }

	      return (0, _utils.convertToSvgRect)({
	        y: r.top + offset - boundingRect.top,
	        x: r.left - boundingRect.left,
	        width: r.width,
	        height: r.height
	      }, svg);
	    }).filter(function (r) {
	      return r.width > 0 && r.height > 0 && r.x > -1 && r.y > -1;
	    })
	  };

	  // Short circuit if no rectangles exist
	  if (annotation.rectangles.length === 0) {
	    return;
	  }

	  // Special treatment for area as it only supports a single rect
	  if (type === 'area') {
	    var rect = annotation.rectangles[0];
	    delete annotation.rectangles;
	    annotation.x = rect.x;
	    annotation.y = rect.y;
	    annotation.width = rect.width;
	    annotation.height = rect.height;
	  }

	  var _getMetadata = (0, _utils.getMetadata)(svg),
	      documentId = _getMetadata.documentId,
	      pageNumber = _getMetadata.pageNumber;

	  // Add the annotation


	  _PDFJSAnnotate2.default.getStoreAdapter().addAnnotation(documentId, pageNumber, annotation).then(function (annotation) {
	    (0, _appendChild.appendChild)(svg, annotation);
	  });
	}

	/**
	 * Enable rect behavior
	 */
	function enableRect(type) {
	  _type = type;

	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  document.addEventListener('mouseup', handleDocumentMouseup);
	  document.addEventListener('mousedown', handleDocumentMousedown);
	  document.addEventListener('keyup', handleDocumentKeyup);
	}

	/**
	 * Disable rect behavior
	 */
	function disableRect() {
	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	  document.removeEventListener('mousedown', handleDocumentMousedown);
	  document.removeEventListener('keyup', handleDocumentKeyup);
	}

/***/ },
/* 113 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.setCircle = setCircle;
	exports.enableCircle = enableCircle;
	exports.disableCircle = disableCircle;
	exports.addCircle = addCircle;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _appendChild = __webpack_require__(83);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var _enabled = false;
	var _type = void 0;
	var _circleRadius = 10;
	var _circleColor = '0000FF';

	/**
	 * Set the attributes of the pen.
	 *
	 * @param {Number} circleRadius The radius of the circle
	 * @param {String} circleColor The color of the circle
	 */
	function setCircle() {
	  var circleRadius = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 10;
	  var circleColor = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '0000FF';

	  _circleRadius = parseInt(circleRadius, 10);
	  _circleColor = circleColor;
	}

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleDocumentMouseup(e) {
	  var svg = (0, _utils.findSVGAtPoint)(e.clientX, e.clientY);
	  if (!svg) {
	    return;
	  }
	  var rect = svg.getBoundingClientRect();
	  saveCircle(svg, _type, {
	    x: e.clientX - rect.left,
	    y: e.clientY - rect.top
	  }, _circleRadius, _circleColor);
	}

	/**
	 * Save a circle annotation
	 *
	 * @param {String} type The type of circle (circle, emptycircle, fillcircle)
	 * @param {Object} pt The point to use for annotation
	 * @param {String} color The color of the rects
	 */
	function saveCircle(svg, type, pt, radius, color) {
	  // Initialize the annotation
	  var svg_pt = (0, _utils.convertToSvgPoint)([pt.x, pt.y], svg);
	  var annotation = {
	    type: type,
	    color: color,
	    cx: svg_pt[0],
	    cy: svg_pt[1],
	    r: radius
	  };

	  var _getMetadata = (0, _utils.getMetadata)(svg),
	      documentId = _getMetadata.documentId,
	      pageNumber = _getMetadata.pageNumber;

	  // Add the annotation


	  _PDFJSAnnotate2.default.getStoreAdapter().addAnnotation(documentId, pageNumber, annotation).then(function (annotation) {
	    (0, _appendChild.appendChild)(svg, annotation);
	  });
	}

	/**
	 * Enable circle behavior
	 */
	function enableCircle(type) {
	  _type = type;

	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  document.addEventListener('mouseup', handleDocumentMouseup);
	}

	/**
	 * Disable circle behavior
	 */
	function disableCircle() {
	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	}

	function addCircle(type, e) {
	  var oldType = _type;
	  _type = type;
	  handleDocumentMouseup(e);
	  _type = oldType;
	}

/***/ },
/* 114 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.setText = setText;
	exports.enableText = enableText;
	exports.disableText = disableText;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _appendChild = __webpack_require__(83);

	var _utils = __webpack_require__(81);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	var _enabled = false;
	var input = void 0;
	var _textSize = void 0;
	var _textColor = void 0;

	/**
	 * Handle document.mouseup event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleDocumentMouseup(e) {
	  if (input || !(0, _utils.findSVGAtPoint)(e.clientX, e.clientY)) {
	    return;
	  }

	  input = document.createElement('input');
	  input.setAttribute('id', 'pdf-annotate-text-input');
	  input.setAttribute('placeholder', 'Enter text');
	  input.style.border = '3px solid ' + _utils.BORDER_COLOR;
	  input.style.borderRadius = '3px';
	  input.style.position = 'absolute';
	  input.style.top = e.clientY + 'px';
	  input.style.left = e.clientX + 'px';
	  input.style.fontSize = _textSize + 'px';

	  input.addEventListener('blur', handleInputBlur);
	  input.addEventListener('keyup', handleInputKeyup);

	  document.body.appendChild(input);
	  input.focus();
	}

	/**
	 * Handle input.blur event
	 */
	function handleInputBlur() {
	  saveText();
	}

	/**
	 * Handle input.keyup event
	 *
	 * @param {Event} e The DOM event to handle
	 */
	function handleInputKeyup(e) {
	  if (e.keyCode === 27) {
	    closeInput();
	  } else if (e.keyCode === 13) {
	    saveText();
	  }
	}

	/**
	 * Save a text annotation from input
	 */
	function saveText() {
	  if (input.value.trim().length > 0) {
	    var clientX = parseInt(input.style.left, 10);
	    var clientY = parseInt(input.style.top, 10);
	    var svg = (0, _utils.findSVGAtPoint)(clientX, clientY);
	    if (!svg) {
	      return;
	    }
	    var height = _textSize;

	    var _getMetadata = (0, _utils.getMetadata)(svg),
	        documentId = _getMetadata.documentId,
	        pageNumber = _getMetadata.pageNumber,
	        viewport = _getMetadata.viewport;

	    var scale = 1 / viewport.scale;
	    var rect = svg.getBoundingClientRect();
	    var pt = (0, _utils.convertToSvgPoint)([clientX - rect.left, clientY - rect.top + height], svg, viewport);
	    var annotation = {
	      type: 'textbox',
	      size: _textSize * scale,
	      color: _textColor,
	      content: input.value.trim(),
	      x: pt[0],
	      y: pt[1],
	      rotation: -viewport.rotation
	    };

	    _PDFJSAnnotate2.default.getStoreAdapter().addAnnotation(documentId, pageNumber, annotation).then(function (annotation) {
	      (0, _appendChild.appendChild)(svg, annotation);
	    });
	  }

	  closeInput();
	}

	/**
	 * Close the input
	 */
	function closeInput() {
	  if (input) {
	    input.removeEventListener('blur', handleInputBlur);
	    input.removeEventListener('keyup', handleInputKeyup);
	    document.body.removeChild(input);
	    input = null;
	  }
	}

	/**
	 * Set the text attributes
	 *
	 * @param {Number} textSize The size of the text
	 * @param {String} textColor The color of the text
	 */
	function setText() {
	  var textSize = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 12;
	  var textColor = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '000000';

	  _textSize = parseInt(textSize, 10);
	  _textColor = textColor;
	}

	/**
	 * Enable text behavior
	 */
	function enableText() {
	  if (_enabled) {
	    return;
	  }

	  _enabled = true;
	  document.addEventListener('mouseup', handleDocumentMouseup);
	}

	/**
	 * Disable text behavior
	 */
	function disableText() {
	  if (!_enabled) {
	    return;
	  }

	  _enabled = false;
	  document.removeEventListener('mouseup', handleDocumentMouseup);
	}

/***/ },
/* 115 */
/***/ function(module, exports, __webpack_require__) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});

	var _slicedToArray = function () { function sliceIterator(arr, i) { var _arr = []; var _n = true; var _d = false; var _e = undefined; try { for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"]) _i["return"](); } finally { if (_d) throw _e; } } return _arr; } return function (arr, i) { if (Array.isArray(arr)) { return arr; } else if (Symbol.iterator in Object(arr)) { return sliceIterator(arr, i); } else { throw new TypeError("Invalid attempt to destructure non-iterable instance"); } }; }();

	exports.createPage = createPage;
	exports.renderPage = renderPage;

	var _PDFJSAnnotate = __webpack_require__(76);

	var _PDFJSAnnotate2 = _interopRequireDefault(_PDFJSAnnotate);

	var _config = __webpack_require__(102);

	var _config2 = _interopRequireDefault(_config);

	var _renderScreenReaderHints = __webpack_require__(98);

	var _renderScreenReaderHints2 = _interopRequireDefault(_renderScreenReaderHints);

	function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

	// Template for creating a new page
	var PAGE_TEMPLATE = '\n  <div style="visibility: hidden;" class="page" data-loaded="false">\n    <div class="canvasWrapper">\n      <canvas></canvas>\n    </div>\n    <div class="' + _config2.default.textLayerName + '"></div>\n    <svg class="' + _config2.default.annotationLayerName + '"></svg>\n  </div>\n';

	/**
	 * Create a new page to be appended to the DOM.
	 *
	 * @param {Number} pageNumber The page number that is being created
	 * @return {HTMLElement}
	 */
	function createPage(pageNumber) {
	  var temp = document.createElement('div');
	  temp.innerHTML = PAGE_TEMPLATE;

	  var page = temp.children[0];
	  var canvas = page.querySelector('canvas');

	  page.setAttribute('id', 'pageContainer' + pageNumber);
	  page.setAttribute('data-page-number', pageNumber);

	  canvas.mozOpaque = true;
	  canvas.setAttribute('id', 'page' + pageNumber);

	  return page;
	}

	/**
	 * Render a page that has already been created.
	 *
	 * @param {Number} pageNumber The page number to be rendered
	 * @param {Object} renderOptions The options for rendering
	 * @return {Promise} Settled once rendering has completed
	 *  A settled Promise will be either:
	 *    - fulfilled: [pdfPage, annotations]
	 *    - rejected: Error
	 */
	function renderPage(pageNumber, renderOptions) {
	  var documentId = renderOptions.documentId,
	      pdfDocument = renderOptions.pdfDocument,
	      scale = renderOptions.scale,
	      rotate = renderOptions.rotate;

	  // Load the page and annotations

	  return Promise.all([pdfDocument.getPage(pageNumber), _PDFJSAnnotate2.default.getAnnotations(documentId, pageNumber)]).then(function (_ref) {
	    var _ref2 = _slicedToArray(_ref, 2),
	        pdfPage = _ref2[0],
	        annotations = _ref2[1];

	    var page = document.getElementById('pageContainer' + pageNumber);
	    var svg = page.querySelector(_config2.default.annotationClassQuery());
	    var canvas = page.querySelector('.canvasWrapper canvas');
	    var canvasContext = canvas.getContext('2d', { alpha: false });
	    var totalRotation = (rotate + pdfPage.rotate) % 360;
	    var viewport = pdfPage.getViewport(scale, totalRotation);
	    var transform = scalePage(pageNumber, viewport, canvasContext);

	    // Render the page
	    return Promise.all([pdfPage.render({ canvasContext: canvasContext, viewport: viewport, transform: transform }), _PDFJSAnnotate2.default.render(svg, viewport, annotations)]).then(function () {
	      // Text content is needed for a11y, but is also necessary for creating
	      // highlight and strikeout annotations which require selecting text.
	      return pdfPage.getTextContent({ normalizeWhitespace: true }).then(function (textContent) {
	        return new Promise(function (resolve, reject) {
	          // Render text layer for a11y of text content
	          var textLayer = page.querySelector(_config2.default.textClassQuery());
	          var textLayerFactory = new PDFJS.DefaultTextLayerFactory();
	          var textLayerBuilder = textLayerFactory.createTextLayerBuilder(textLayer, pageNumber - 1, viewport);
	          textLayerBuilder.setTextContent(textContent);
	          textLayerBuilder.render();

	          // Enable a11y for annotations
	          // Timeout is needed to wait for `textLayerBuilder.render`
	          setTimeout(function () {
	            try {
	              (0, _renderScreenReaderHints2.default)(annotations.annotations);
	              resolve();
	            } catch (e) {
	              reject(e);
	            }
	          });
	        });
	      });
	    }).then(function () {
	      // Indicate that the page was loaded
	      page.setAttribute('data-loaded', 'true');

	      return [pdfPage, annotations];
	    });
	  });
	}

	/**
	 * Scale the elements of a page.
	 *
	 * @param {Number} pageNumber The page number to be scaled
	 * @param {Object} viewport The viewport of the PDF page (see pdfPage.getViewport(scale, rotate))
	 * @param {Object} context The canvas context that the PDF page is rendered to
	 * @return {Array} The transform data for rendering the PDF page
	 */
	function scalePage(pageNumber, viewport, context) {
	  var page = document.getElementById('pageContainer' + pageNumber);
	  var canvas = page.querySelector('.canvasWrapper canvas');
	  var svg = page.querySelector(_config2.default.annotationClassQuery());
	  var wrapper = page.querySelector('.canvasWrapper');
	  var textLayer = page.querySelector(_config2.default.textClassQuery());
	  var outputScale = getOutputScale(context);
	  var transform = !outputScale.scaled ? null : [outputScale.sx, 0, 0, outputScale.sy, 0, 0];
	  var sfx = approximateFraction(outputScale.sx);
	  var sfy = approximateFraction(outputScale.sy);

	  // Adjust width/height for scale
	  page.style.visibility = '';
	  canvas.width = roundToDivide(viewport.width * outputScale.sx, sfx[0]);
	  canvas.height = roundToDivide(viewport.height * outputScale.sy, sfy[0]);
	  canvas.style.width = roundToDivide(viewport.width, sfx[1]) + 'px';
	  canvas.style.height = roundToDivide(viewport.height, sfx[1]) + 'px';
	  svg.setAttribute('width', viewport.width);
	  svg.setAttribute('height', viewport.height);
	  svg.style.width = viewport.width + 'px';
	  svg.style.height = viewport.height + 'px';
	  page.style.width = viewport.width + 'px';
	  page.style.height = viewport.height + 'px';
	  wrapper.style.width = viewport.width + 'px';
	  wrapper.style.height = viewport.height + 'px';
	  textLayer.style.width = viewport.width + 'px';
	  textLayer.style.height = viewport.height + 'px';

	  return transform;
	}

	/**
	 * Approximates a float number as a fraction using Farey sequence (max order of 8).
	 *
	 * @param {Number} x Positive float number
	 * @return {Array} Estimated fraction: the first array item is a numerator,
	 *                 the second one is a denominator.
	 */
	function approximateFraction(x) {
	  // Fast path for int numbers or their inversions.
	  if (Math.floor(x) === x) {
	    return [x, 1];
	  }

	  var xinv = 1 / x;
	  var limit = 8;
	  if (xinv > limit) {
	    return [1, limit];
	  } else if (Math.floor(xinv) === xinv) {
	    return [1, xinv];
	  }

	  var x_ = x > 1 ? xinv : x;

	  // a/b and c/d are neighbours in Farey sequence.
	  var a = 0,
	      b = 1,
	      c = 1,
	      d = 1;

	  // Limit search to order 8.
	  while (true) {
	    // Generating next term in sequence (order of q).
	    var p = a + c,
	        q = b + d;
	    if (q > limit) {
	      break;
	    }
	    if (x_ <= p / q) {
	      c = p;d = q;
	    } else {
	      a = p;b = q;
	    }
	  }

	  // Select closest of neighbours to x.
	  if (x_ - a / b < c / d - x_) {
	    return x_ === x ? [a, b] : [b, a];
	  } else {
	    return x_ === x ? [c, d] : [d, c];
	  }
	}

	function getOutputScale(ctx) {
	  var devicePixelRatio = window.devicePixelRatio || 1;
	  var backingStoreRatio = ctx.webkitBackingStorePixelRatio || ctx.mozBackingStorePixelRatio || ctx.msBackingStorePixelRatio || ctx.oBackingStorePixelRatio || ctx.backingStorePixelRatio || 1;
	  var pixelRatio = devicePixelRatio / backingStoreRatio;
	  return {
	    sx: pixelRatio,
	    sy: pixelRatio,
	    scaled: pixelRatio !== 1
	  };
	}

	function roundToDivide(x, div) {
	  var r = x % div;
	  return r === 0 ? x : Math.round(x - r + div);
	}

/***/ },
/* 116 */
/***/ function(module, exports) {

	'use strict';

	Object.defineProperty(exports, "__esModule", {
	  value: true
	});
	exports.default = initColorPicker;
	// Color picker component
	var COLORS = [{ hex: 'darkgoldenrod', name: 'Delete Color' }, { hex: '#000000', name: 'Black' }, { hex: '#EF4437', name: 'Red' }, { hex: '#E71F63', name: 'Pink' }, { hex: '#8F3E97', name: 'Purple' }, { hex: '#65499D', name: 'Deep Purple' }, { hex: '#4554A4', name: 'Indigo' }, { hex: '#2083C5', name: 'Blue' }, { hex: '#35A4DC', name: 'Light Blue' }, { hex: '#09BCD3', name: 'Cyan' }, { hex: '#009688', name: 'Teal' }, { hex: '#43A047', name: 'Green' }, { hex: '#8BC34A', name: 'Light Green' }, { hex: '#FDC010', name: 'Yellow' }, { hex: '#F8971C', name: 'Orange' }, { hex: '#F0592B', name: 'Deep Orange' }, { hex: '#F06291', name: 'Light Pink' }];

	function initColorPicker(el, value, onChange) {
	  function setColor(value) {
	    var fireOnChange = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;

	    currentValue = value;
	    a.setAttribute('data-color', value);
	    a.style.background = value;
	    if (fireOnChange && typeof onChange === 'function') {
	      onChange(value);
	    }
	    closePicker();
	  }

	  function togglePicker() {
	    if (isPickerOpen) {
	      closePicker();
	    } else {
	      openPicker();
	    }
	  }

	  function closePicker() {
	    document.removeEventListener('keyup', handleDocumentKeyup);
	    if (picker && picker.parentNode) {
	      picker.parentNode.removeChild(picker);
	    }
	    isPickerOpen = false;
	    a.focus();
	  }

	  function openPicker() {
	    if (!picker) {
	      picker = document.createElement('div');
	      picker.style.background = '#fff';
	      picker.style.border = '1px solid #ccc';
	      picker.style.padding = '2px';
	      picker.style.position = 'absolute';
	      picker.style.width = '122px';
	      el.style.position = 'relative';

	      COLORS.map(createColorOption).forEach(function (c) {
	        c.style.margin = '2px';
	        c.onclick = function () {
	          setColor(c.getAttribute('data-color'));
	        };
	        picker.appendChild(c);
	      });
	    }

	    document.addEventListener('keyup', handleDocumentKeyup);
	    el.appendChild(picker);
	    isPickerOpen = true;
	  }

	  function createColorOption(color) {
	    var e = document.createElement('a');
	    e.className = 'color';
	    e.setAttribute('href', 'javascript://');
	    e.setAttribute('title', color.name);
	    e.setAttribute('data-color', color.hex);
	    e.style.background = color.hex;
	    return e;
	  }

	  function handleDocumentKeyup(e) {
	    if (e.keyCode === 27) {
	      closePicker();
	    }
	  }

	  var picker = void 0;
	  var isPickerOpen = false;
	  var currentValue = void 0;
	  var a = createColorOption({ hex: value });
	  a.onclick = togglePicker;
	  el.appendChild(a);
	  setColor(value, false);
	}

/***/ }
/******/ ]);
