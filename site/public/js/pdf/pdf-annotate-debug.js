!(function (t, e) {
    "object" == typeof exports && "object" == typeof module
        ? (module.exports = e())
        : "function" == typeof define && define.amd
          ? define([], e)
          : "object" == typeof exports
            ? (exports.PDFAnnotate = e())
            : (t.PDFAnnotate = e());
})(window, function () {
    return (function (t) {
        var e = {};
        function n(r) {
            if (e[r]) return e[r].exports;
            var o = (e[r] = { i: r, l: !1, exports: {} });
            return t[r].call(o.exports, o, o.exports, n), (o.l = !0), o.exports;
        }
        return (
            (n.m = t),
            (n.c = e),
            (n.d = function (t, e, r) {
                n.o(t, e) ||
                    Object.defineProperty(t, e, { enumerable: !0, get: r });
            }),
            (n.r = function (t) {
                "undefined" != typeof Symbol &&
                    Symbol.toStringTag &&
                    Object.defineProperty(t, Symbol.toStringTag, {
                        value: "Module",
                    }),
                    Object.defineProperty(t, "__esModule", { value: !0 });
            }),
            (n.t = function (t, e) {
                if ((1 & e && (t = n(t)), 8 & e)) return t;
                if (4 & e && "object" == typeof t && t && t.__esModule)
                    return t;
                var r = Object.create(null);
                if (
                    (n.r(r),
                    Object.defineProperty(r, "default", {
                        enumerable: !0,
                        value: t,
                    }),
                    2 & e && "string" != typeof t)
                )
                    for (var o in t)
                        n.d(
                            r,
                            o,
                            function (e) {
                                return t[e];
                            }.bind(null, o),
                        );
                return r;
            }),
            (n.n = function (t) {
                var e =
                    t && t.__esModule
                        ? function () {
                              return t.default;
                          }
                        : function () {
                              return t;
                          };
                return n.d(e, "a", e), e;
            }),
            (n.o = function (t, e) {
                return Object.prototype.hasOwnProperty.call(t, e);
            }),
            (n.p = ""),
            n((n.s = 1))
        );
    })([
        function (t, e, n) {
            "use strict";
            var r,
                o = "object" == typeof Reflect ? Reflect : null,
                i =
                    o && "function" == typeof o.apply
                        ? o.apply
                        : function (t, e, n) {
                              return Function.prototype.apply.call(t, e, n);
                          };
            r =
                o && "function" == typeof o.ownKeys
                    ? o.ownKeys
                    : Object.getOwnPropertySymbols
                      ? function (t) {
                            return Object.getOwnPropertyNames(t).concat(
                                Object.getOwnPropertySymbols(t),
                            );
                        }
                      : function (t) {
                            return Object.getOwnPropertyNames(t);
                        };
            var a =
                Number.isNaN ||
                function (t) {
                    return t != t;
                };
            function u() {
                u.init.call(this);
            }
            (t.exports = u),
                (t.exports.once = function (t, e) {
                    return new Promise(function (n, r) {
                        function o(n) {
                            t.removeListener(e, i), r(n);
                        }
                        function i() {
                            "function" == typeof t.removeListener &&
                                t.removeListener("error", o),
                                n([].slice.call(arguments));
                        }
                        h(t, e, i, { once: !0 }),
                            "error" !== e &&
                                (function (t, e, n) {
                                    "function" == typeof t.on &&
                                        h(t, "error", e, n);
                                })(t, o, { once: !0 });
                    });
                }),
                (u.EventEmitter = u),
                (u.prototype._events = void 0),
                (u.prototype._eventsCount = 0),
                (u.prototype._maxListeners = void 0);
            var c = 10;
            function l(t) {
                if ("function" != typeof t)
                    throw new TypeError(
                        'The "listener" argument must be of type Function. Received type ' +
                            typeof t,
                    );
            }
            function s(t) {
                return void 0 === t._maxListeners
                    ? u.defaultMaxListeners
                    : t._maxListeners;
            }
            function d(t, e, n, r) {
                var o, i, a, u;
                if (
                    (l(n),
                    void 0 === (i = t._events)
                        ? ((i = t._events = Object.create(null)),
                          (t._eventsCount = 0))
                        : (void 0 !== i.newListener &&
                              (t.emit(
                                  "newListener",
                                  e,
                                  n.listener ? n.listener : n,
                              ),
                              (i = t._events)),
                          (a = i[e])),
                    void 0 === a)
                )
                    (a = i[e] = n), ++t._eventsCount;
                else if (
                    ("function" == typeof a
                        ? (a = i[e] = r ? [n, a] : [a, n])
                        : r
                          ? a.unshift(n)
                          : a.push(n),
                    (o = s(t)) > 0 && a.length > o && !a.warned)
                ) {
                    a.warned = !0;
                    var c = new Error(
                        "Possible EventEmitter memory leak detected. " +
                            a.length +
                            " " +
                            String(e) +
                            " listeners added. Use emitter.setMaxListeners() to increase limit",
                    );
                    (c.name = "MaxListenersExceededWarning"),
                        (c.emitter = t),
                        (c.type = e),
                        (c.count = a.length),
                        (u = c),
                        console && console.warn && console.warn(u);
                }
                return t;
            }
            function f() {
                if (!this.fired)
                    return (
                        this.target.removeListener(this.type, this.wrapFn),
                        (this.fired = !0),
                        0 === arguments.length
                            ? this.listener.call(this.target)
                            : this.listener.apply(this.target, arguments)
                    );
            }
            function p(t, e, n) {
                var r = {
                        fired: !1,
                        wrapFn: void 0,
                        target: t,
                        type: e,
                        listener: n,
                    },
                    o = f.bind(r);
                return (o.listener = n), (r.wrapFn = o), o;
            }
            function y(t, e, n) {
                var r = t._events;
                if (void 0 === r) return [];
                var o = r[e];
                return void 0 === o
                    ? []
                    : "function" == typeof o
                      ? n
                          ? [o.listener || o]
                          : [o]
                      : n
                        ? (function (t) {
                              for (
                                  var e = new Array(t.length), n = 0;
                                  n < e.length;
                                  ++n
                              )
                                  e[n] = t[n].listener || t[n];
                              return e;
                          })(o)
                        : v(o, o.length);
            }
            function m(t) {
                var e = this._events;
                if (void 0 !== e) {
                    var n = e[t];
                    if ("function" == typeof n) return 1;
                    if (void 0 !== n) return n.length;
                }
                return 0;
            }
            function v(t, e) {
                for (var n = new Array(e), r = 0; r < e; ++r) n[r] = t[r];
                return n;
            }
            function h(t, e, n, r) {
                if ("function" == typeof t.on)
                    r.once ? t.once(e, n) : t.on(e, n);
                else {
                    if ("function" != typeof t.addEventListener)
                        throw new TypeError(
                            'The "emitter" argument must be of type EventEmitter. Received type ' +
                                typeof t,
                        );
                    t.addEventListener(e, function o(i) {
                        r.once && t.removeEventListener(e, o), n(i);
                    });
                }
            }
            Object.defineProperty(u, "defaultMaxListeners", {
                enumerable: !0,
                get: function () {
                    return c;
                },
                set: function (t) {
                    if ("number" != typeof t || t < 0 || a(t))
                        throw new RangeError(
                            'The value of "defaultMaxListeners" is out of range. It must be a non-negative number. Received ' +
                                t +
                                ".",
                        );
                    c = t;
                },
            }),
                (u.init = function () {
                    (void 0 !== this._events &&
                        this._events !== Object.getPrototypeOf(this)._events) ||
                        ((this._events = Object.create(null)),
                        (this._eventsCount = 0)),
                        (this._maxListeners = this._maxListeners || void 0);
                }),
                (u.prototype.setMaxListeners = function (t) {
                    if ("number" != typeof t || t < 0 || a(t))
                        throw new RangeError(
                            'The value of "n" is out of range. It must be a non-negative number. Received ' +
                                t +
                                ".",
                        );
                    return (this._maxListeners = t), this;
                }),
                (u.prototype.getMaxListeners = function () {
                    return s(this);
                }),
                (u.prototype.emit = function (t) {
                    for (var e = [], n = 1; n < arguments.length; n++)
                        e.push(arguments[n]);
                    var r = "error" === t,
                        o = this._events;
                    if (void 0 !== o) r = r && void 0 === o.error;
                    else if (!r) return !1;
                    if (r) {
                        var a;
                        if ((e.length > 0 && (a = e[0]), a instanceof Error))
                            throw a;
                        var u = new Error(
                            "Unhandled error." +
                                (a ? " (" + a.message + ")" : ""),
                        );
                        throw ((u.context = a), u);
                    }
                    var c = o[t];
                    if (void 0 === c) return !1;
                    if ("function" == typeof c) i(c, this, e);
                    else {
                        var l = c.length,
                            s = v(c, l);
                        for (n = 0; n < l; ++n) i(s[n], this, e);
                    }
                    return !0;
                }),
                (u.prototype.addListener = function (t, e) {
                    return d(this, t, e, !1);
                }),
                (u.prototype.on = u.prototype.addListener),
                (u.prototype.prependListener = function (t, e) {
                    return d(this, t, e, !0);
                }),
                (u.prototype.once = function (t, e) {
                    return l(e), this.on(t, p(this, t, e)), this;
                }),
                (u.prototype.prependOnceListener = function (t, e) {
                    return l(e), this.prependListener(t, p(this, t, e)), this;
                }),
                (u.prototype.removeListener = function (t, e) {
                    var n, r, o, i, a;
                    if ((l(e), void 0 === (r = this._events))) return this;
                    if (void 0 === (n = r[t])) return this;
                    if (n === e || n.listener === e)
                        0 == --this._eventsCount
                            ? (this._events = Object.create(null))
                            : (delete r[t],
                              r.removeListener &&
                                  this.emit(
                                      "removeListener",
                                      t,
                                      n.listener || e,
                                  ));
                    else if ("function" != typeof n) {
                        for (o = -1, i = n.length - 1; i >= 0; i--)
                            if (n[i] === e || n[i].listener === e) {
                                (a = n[i].listener), (o = i);
                                break;
                            }
                        if (o < 0) return this;
                        0 === o
                            ? n.shift()
                            : (function (t, e) {
                                  for (; e + 1 < t.length; e++) t[e] = t[e + 1];
                                  t.pop();
                              })(n, o),
                            1 === n.length && (r[t] = n[0]),
                            void 0 !== r.removeListener &&
                                this.emit("removeListener", t, a || e);
                    }
                    return this;
                }),
                (u.prototype.off = u.prototype.removeListener),
                (u.prototype.removeAllListeners = function (t) {
                    var e, n, r;
                    if (void 0 === (n = this._events)) return this;
                    if (void 0 === n.removeListener)
                        return (
                            0 === arguments.length
                                ? ((this._events = Object.create(null)),
                                  (this._eventsCount = 0))
                                : void 0 !== n[t] &&
                                  (0 == --this._eventsCount
                                      ? (this._events = Object.create(null))
                                      : delete n[t]),
                            this
                        );
                    if (0 === arguments.length) {
                        var o,
                            i = Object.keys(n);
                        for (r = 0; r < i.length; ++r)
                            "removeListener" !== (o = i[r]) &&
                                this.removeAllListeners(o);
                        return (
                            this.removeAllListeners("removeListener"),
                            (this._events = Object.create(null)),
                            (this._eventsCount = 0),
                            this
                        );
                    }
                    if ("function" == typeof (e = n[t]))
                        this.removeListener(t, e);
                    else if (void 0 !== e)
                        for (r = e.length - 1; r >= 0; r--)
                            this.removeListener(t, e[r]);
                    return this;
                }),
                (u.prototype.listeners = function (t) {
                    return y(this, t, !0);
                }),
                (u.prototype.rawListeners = function (t) {
                    return y(this, t, !1);
                }),
                (u.listenerCount = function (t, e) {
                    return "function" == typeof t.listenerCount
                        ? t.listenerCount(e)
                        : m.call(t, e);
                }),
                (u.prototype.listenerCount = m),
                (u.prototype.eventNames = function () {
                    return this._eventsCount > 0 ? r(this._events) : [];
                });
        },
        function (t, e, n) {
            "use strict";
            function r(t) {
                throw new Error(t + " is not implemented");
            }
            n.r(e);
            var o = n(0),
                i = n.n(o),
                a = /[A-Z]/g,
                u = ["viewBox"];
            function c(t, e) {
                Object.keys(e).forEach(function (n) {
                    t.setAttribute(
                        (function (t) {
                            return (
                                -1 === u.indexOf(t) &&
                                    (t = t.replace(a, function (t) {
                                        return "-" + t.toLowerCase();
                                    })),
                                t
                            );
                        })(n),
                        e[n],
                    );
                });
            }
            var l = /^([a-f0-9]{6}|[a-f0-9]{3})$/i;
            function s(t) {
                return l.test(t) && (t = "#".concat(t)), t;
            }
            var d = 25;
            function f(t) {
                var e = document.createElementNS(
                    "http://www.w3.org/2000/svg",
                    "rect",
                );
                return (
                    c(e, { x: t.x, y: t.y, width: t.width, height: t.height }),
                    e
                );
            }
            function p(t, e) {
                var n =
                    ("undefined" != typeof Symbol && t[Symbol.iterator]) ||
                    t["@@iterator"];
                if (!n) {
                    if (
                        Array.isArray(t) ||
                        (n = (function (t, e) {
                            if (!t) return;
                            if ("string" == typeof t) return y(t, e);
                            var n = Object.prototype.toString
                                .call(t)
                                .slice(8, -1);
                            "Object" === n &&
                                t.constructor &&
                                (n = t.constructor.name);
                            if ("Map" === n || "Set" === n)
                                return Array.from(t);
                            if (
                                "Arguments" === n ||
                                /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
                                    n,
                                )
                            )
                                return y(t, e);
                        })(t)) ||
                        (e && t && "number" == typeof t.length)
                    ) {
                        n && (t = n);
                        var r = 0,
                            o = function () {};
                        return {
                            s: o,
                            n: function () {
                                return r >= t.length
                                    ? { done: !0 }
                                    : { done: !1, value: t[r++] };
                            },
                            e: function (t) {
                                throw t;
                            },
                            f: o,
                        };
                    }
                    throw new TypeError(
                        "Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                    );
                }
                var i,
                    a = !0,
                    u = !1;
                return {
                    s: function () {
                        n = n.call(t);
                    },
                    n: function () {
                        var t = n.next();
                        return (a = t.done), t;
                    },
                    e: function (t) {
                        (u = !0), (i = t);
                    },
                    f: function () {
                        try {
                            a || null == n.return || n.return();
                        } finally {
                            if (u) throw i;
                        }
                    },
                };
            }
            function y(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            function m(t) {
                var e = document.createElementNS(
                    "http://www.w3.org/2000/svg",
                    "text",
                );
                c(e, {
                    x: t.x,
                    y: t.y,
                    fill: s(t.color || "#000"),
                    fontSize: t.size,
                    transform: "rotate(".concat(t.rotation, ")"),
                    style: "white-space: pre",
                }),
                    (function (t, e) {
                        var n,
                            r = (e || "").split("\n"),
                            o = t.getAttribute("x"),
                            i = Number(t.getAttribute("y")),
                            a = Number(t.getAttribute("font-size")),
                            u = p(r);
                        try {
                            for (u.s(); !(n = u.n()).done; ) {
                                var c = n.value,
                                    l = document.createElementNS(
                                        "http://www.w3.org/2000/svg",
                                        "tspan",
                                    );
                                l.setAttribute("y", i.toString()),
                                    l.setAttribute("x", o),
                                    (l.innerHTML = c),
                                    t.appendChild(l),
                                    (i += a);
                            }
                        } catch (t) {
                            u.e(t);
                        } finally {
                            u.f();
                        }
                    })(e, t.content);
                var n = document.createElementNS(
                    "http://www.w3.org/2000/svg",
                    "g",
                );
                return n.appendChild(e), n;
            }
            function v(t) {
                var e = (function (t) {
                        var e = document.createElementNS(
                            "http://www.w3.org/2000/svg",
                            "circle",
                        );
                        return c(e, { cx: t.cx, cy: t.cy, r: t.r }), e;
                    })(t),
                    n = s(t.color || "#f00");
                return (
                    "circle" === t.type &&
                        c(e, { stroke: n, fill: "none", "stroke-width": 5 }),
                    "emptycircle" === t.type &&
                        c(e, { stroke: n, fill: "none", "stroke-width": 2 }),
                    "fillcircle" === t.type &&
                        c(e, { stroke: n, fill: n, "stroke-width": 5 }),
                    e
                );
            }
            function h(t, e, n) {
                return [
                    t[0],
                    t[1],
                    t[2],
                    t[3],
                    t[0] * e + t[2] * n + t[4],
                    t[1] * e + t[3] * n + t[5],
                ];
            }
            function g(t, e) {
                e = (e * Math.PI) / 180;
                var n = Math.cos(e),
                    r = Math.sin(e);
                return [
                    t[0] * n + t[2] * r,
                    t[1] * n + t[3] * r,
                    t[0] * -r + t[2] * n,
                    t[1] * -r + t[3] * n,
                    t[4],
                    t[5],
                ];
            }
            function b(t, e, n) {
                return [t[0] * e, t[1] * e, t[2] * n, t[3] * n, t[4], t[5]];
            }
            function w(t, e, n) {
                return { x: t, y: e, z: n };
            }
            function x(t, e, n) {
                return { xcoord: t, ycoord: e, zcoord: n };
            }
            function A(t, e) {
                return x(e.x - t.x, e.y - t.y, e.z - t.z);
            }
            function S(t, e) {
                return w(t.x + e.xcoord, t.y + e.ycoord, t.z + e.zcoord);
            }
            function E(t, e) {
                return x(t.xcoord * e, t.ycoord * e, t.zcoord * e);
            }
            function C(t) {
                return Math.sqrt(
                    Math.pow(t.xcoord, 2) +
                        Math.pow(t.ycoord, 2) +
                        Math.pow(t.zcoord, 2),
                );
            }
            function L(t) {
                return E(t, -1);
            }
            function I(t) {
                var e = C(t);
                return x(t.xcoord / e, t.ycoord / e, t.zcoord / e);
            }
            function _(t) {
                var e,
                    n,
                    r = document.createElementNS(
                        "http://www.w3.org/2000/svg",
                        "polygon",
                    );
                if (2 === t.lines.length) {
                    var o = t.lines[0],
                        i = t.lines[t.lines.length - 1],
                        a = w(o[0], o[1], 0),
                        u = w(i[0], i[1], 0),
                        l = A(a, u),
                        d = I(l);
                    l = A(a, (u = S(a, E(d, 40))));
                    var f = x(0, 0, 1),
                        p = I(
                            ((n = f),
                            x(
                                (e = d).ycoord * n.zcoord - e.zcoord * n.ycoord,
                                e.zcoord * n.xcoord - e.xcoord * n.zcoord,
                                e.xcoord * n.ycoord - e.ycoord * n.xcoord,
                            )),
                        ),
                        y = t.width || 10,
                        m = S(a, E(p, 0.5 * y)),
                        v = S(m, E(d, C(l) - 2 * y)),
                        h = S(v, E(p, y)),
                        g = u,
                        b = S(a, E(L(p), 0.5 * y)),
                        _ = S(b, E(d, C(l) - 2 * y)),
                        k = S(_, E(L(p), y));
                    c(r, {
                        points:
                            m.x +
                            "," +
                            m.y +
                            " " +
                            v.x +
                            "," +
                            v.y +
                            " " +
                            h.x +
                            "," +
                            h.y +
                            " " +
                            g.x +
                            "," +
                            g.y +
                            " " +
                            k.x +
                            "," +
                            k.y +
                            " " +
                            _.x +
                            "," +
                            _.y +
                            " " +
                            b.x +
                            "," +
                            b.y,
                        stroke: s(t.color || "#000"),
                        fill: s(t.color || "#000"),
                    });
                }
                return r;
            }
            var k = /firefox/i.test(navigator.userAgent);
            function O(t) {
                var e, n;
                switch (t.rotation % 360) {
                    case 0:
                        e = n = 0;
                        break;
                    case 90:
                        (e = 0), (n = (t.width / t.scale) * -1);
                        break;
                    case 180:
                        (e = (t.width / t.scale) * -1),
                            (n = (t.height / t.scale) * -1);
                        break;
                    case 270:
                        (e = (t.height / t.scale) * -1), (n = 0);
                }
                return { x: e, y: n };
            }
            function N(t, e) {
                var n = O(e);
                if (
                    (t.setAttribute(
                        "transform",
                        "scale("
                            .concat(e.scale, ") rotate(")
                            .concat(e.rotation, ") translate(")
                            .concat(n.x, ", ")
                            .concat(n.y, ")"),
                    ),
                    !k && "svg" === t.nodeName.toLowerCase())
                ) {
                    t.setAttribute(
                        "x",
                        parseInt(t.getAttribute("x"), 10) * e.scale,
                    ),
                        t.setAttribute(
                            "y",
                            parseInt(t.getAttribute("y"), 10) * e.scale,
                        );
                    var r = parseInt(t.getAttribute("x", 10)),
                        o = parseInt(t.getAttribute("y", 10)),
                        i = parseInt(t.getAttribute("width"), 10),
                        a = parseInt(t.getAttribute("height"), 10),
                        u = t.querySelector("path"),
                        c = u.parentNode;
                    switch (
                        ([t, c, u, t.querySelector("rect")].forEach(
                            function (t) {
                                t.setAttribute(
                                    "width",
                                    parseInt(t.getAttribute("width"), 10) *
                                        e.scale,
                                ),
                                    t.setAttribute(
                                        "height",
                                        parseInt(t.getAttribute("height"), 10) *
                                            e.scale,
                                    );
                            },
                        ),
                        N(u, Object.assign({}, e, { scale: 1 })),
                        e.rotation % 360)
                    ) {
                        case 90:
                            t.setAttribute("x", e.width - o - i),
                                t.setAttribute("y", r),
                                c.setAttribute("x", 1),
                                c.setAttribute("y", 0);
                            break;
                        case 180:
                            t.setAttribute("x", e.width - r - i),
                                t.setAttribute("y", e.height - o - a),
                                c.setAttribute("y", 2);
                            break;
                        case 270:
                            t.setAttribute("x", o),
                                t.setAttribute("y", e.height - r - a),
                                c.setAttribute("x", -1),
                                c.setAttribute("y", 0);
                    }
                }
                return t;
            }
            function j(t, e, n) {
                var r, o, i;
                switch (
                    (n ||
                        (n = JSON.parse(
                            t.getAttribute("data-pdf-annotate-viewport"),
                        )),
                    e.type)
                ) {
                    case "area":
                    case "highlight":
                        r = (function (t) {
                            if ("highlight" === t.type) {
                                var e = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "g",
                                );
                                return (
                                    c(e, {
                                        fill: s(t.color || "#ff0"),
                                        fillOpacity: 0.2,
                                    }),
                                    t.rectangles.forEach(function (t) {
                                        e.appendChild(f(t));
                                    }),
                                    e
                                );
                            }
                            var n = f(t);
                            return (
                                c(n, {
                                    stroke: s(t.color || "#f00"),
                                    fill: "none",
                                }),
                                n
                            );
                        })(e);
                        break;
                    case "circle":
                    case "fillcircle":
                    case "emptycircle":
                        r = v(e);
                        break;
                    case "strikeout":
                        (o = e),
                            c(
                                (i = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "g",
                                )),
                                {
                                    stroke: s(o.color || "#f00"),
                                    strokeWidth: 1,
                                },
                            ),
                            o.rectangles.forEach(function (t) {
                                var e = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "line",
                                );
                                c(e, {
                                    x1: t.x,
                                    y1: t.y,
                                    x2: t.x + t.width,
                                    y2: t.y,
                                }),
                                    i.appendChild(e);
                            }),
                            (r = i);
                        break;
                    case "point":
                        r = (function (t) {
                            var e = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "svg",
                                ),
                                n = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "svg",
                                ),
                                r = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "rect",
                                ),
                                o = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "path",
                                );
                            return (
                                c(e, { width: d, height: d, x: t.x, y: t.y }),
                                c(n, {
                                    width: d,
                                    height: d,
                                    x: 0,
                                    y: -1.25,
                                    viewBox: "0 0 1000 1000",
                                }),
                                c(r, {
                                    width: d,
                                    height: d,
                                    stroke: "#000",
                                    fill: "#ff0",
                                }),
                                c(o, {
                                    d: "M499.968 214.336q-113.832 0 -212.877 38.781t-157.356 104.625 -58.311 142.29q0 62.496 39.897 119.133t112.437 97.929l48.546 27.9 -15.066 53.568q-13.392 50.778 -39.06 95.976 84.816 -35.154 153.45 -95.418l23.994 -21.204 31.806 3.348q38.502 4.464 72.54 4.464 113.832 0 212.877 -38.781t157.356 -104.625 58.311 -142.29 -58.311 -142.29 -157.356 -104.625 -212.877 -38.781z",
                                    strokeWidth: 50,
                                    stroke: "#000",
                                    fill: "#fff",
                                }),
                                n.appendChild(o),
                                e.appendChild(r),
                                e.appendChild(n),
                                e
                            );
                        })(e);
                        break;
                    case "textbox":
                        r = m(e);
                        break;
                    case "drawing":
                        r = (function (t) {
                            var e = [],
                                n = document.createElementNS(
                                    "http://www.w3.org/2000/svg",
                                    "path",
                                );
                            if (t.lines.length > 0) {
                                e.push(
                                    "M"
                                        .concat(t.lines[0][0], " ")
                                        .concat(t.lines[0][1]),
                                );
                                for (
                                    var r = 1, o = t.lines.length;
                                    r < o;
                                    r++
                                ) {
                                    var i = t.lines[r];
                                    t.lines[r + 1] &&
                                        e.push(
                                            "L".concat(i[0], " ").concat(i[1]),
                                        );
                                }
                            }
                            return (
                                c(n, {
                                    d: "".concat(e.join(" ")),
                                    stroke: s(t.color || "#000"),
                                    strokeWidth: t.width || 1,
                                    fill: "none",
                                }),
                                n
                            );
                        })(e);
                        break;
                    case "arrow":
                        r = _(e);
                }
                return (
                    r &&
                        (r.setAttribute("data-pdf-annotate-id", e.uuid),
                        r.setAttribute("aria-hidden", !0),
                        Object.keys(e)
                            .filter(function (t) {
                                return (
                                    -1 ===
                                    [
                                        "color",
                                        "x",
                                        "y",
                                        "cx",
                                        "cy",
                                        "color",
                                        "documentId",
                                        "lines",
                                        "page",
                                        "width",
                                        "class",
                                        "content",
                                        "size",
                                        "rotation",
                                        "r",
                                    ].indexOf(t)
                                );
                            })
                            .forEach(function (t) {
                                r.setAttribute(
                                    "data-pdf-annotate-".concat(t),
                                    e[t],
                                );
                            }),
                        t.appendChild(N(r, n))),
                    r
                );
            }
            function P(t, e, n) {
                return (
                    n ||
                        (n = JSON.parse(
                            t.getAttribute("data-pdf-annotate-viewport"),
                        )),
                    e && (e = N(e, n)),
                    e
                );
            }
            var R = "#00BFFF",
                M = document.createElement("style");
            function T(t) {
                for (var e = t; (e = e.parentNode) && e !== document; )
                    if (
                        "SVG" === e.nodeName.toUpperCase() &&
                        "true" === e.getAttribute("data-pdf-annotate-container")
                    )
                        return e;
                return null;
            }
            function B(t, e) {
                for (
                    var n = document.querySelectorAll(
                            'svg[data-pdf-annotate-container="true"]',
                        ),
                        r = 0,
                        o = n.length;
                    r < o;
                    r++
                ) {
                    var i = n[r];
                    if (z(t, e, i.getBoundingClientRect())) return i;
                }
                return null;
            }
            function q(t, e) {
                for (
                    var n = null, r = document.elementFromPoint(t, e);
                    !n && r && r !== document;

                ) {
                    r.getAttribute("data-pdf-annotate-type") && (n = r),
                        (r = r.parentNode);
                }
                return n;
            }
            function z(t, e, n) {
                return (
                    e >= n.top && e <= n.bottom && t >= n.left && t <= n.right
                );
            }
            function F(t) {
                var e = t.getBoundingClientRect(),
                    n = e.width,
                    r = e.height,
                    o = 0,
                    i = 0;
                if (
                    ["line", "path"].indexOf(t.tagName.toLowerCase()) > -1 &&
                    t.getBBox
                ) {
                    var a = t.getBBox();
                    (o = (e.width - a.width) / 2),
                        (i = (e.height - a.height) / 2),
                        (n = a.width),
                        (r = a.height);
                }
                var u = (function (t) {
                        var e = t;
                        for (
                            ;
                            (e = e.parentNode) &&
                            e !== document &&
                            "SVG" !== e.nodeName.toUpperCase();

                        );
                        var n = e.getBoundingClientRect();
                        return { offsetLeft: n.left, offsetTop: n.top };
                    })(t),
                    c = u.offsetLeft,
                    l = u.offsetTop;
                return {
                    top: e.top - l + i,
                    left: e.left - c + o,
                    bottom: e.bottom - l - i,
                    right: e.right - c - o,
                    width: n,
                    height: r,
                };
            }
            function X(t, e) {
                var n = {},
                    r = W(t).viewport;
                return (
                    Object.keys(e).forEach(function (t) {
                        n[t] = e[t] * r.scale;
                    }),
                    n
                );
            }
            function Y(t, e, n) {
                var r = [1, 0, 0, 1, 0, 0];
                r = g(
                    (r = b(r, (n = n || W(e).viewport).scale, n.scale)),
                    n.rotation,
                );
                var o,
                    i,
                    a,
                    u = O(n);
                return (
                    (r = h(r, u.x, u.y)),
                    (o = t),
                    (a = (i = r)[0] * i[3] - i[1] * i[2]),
                    [
                        (o[0] * i[3] -
                            o[1] * i[2] +
                            i[2] * i[5] -
                            i[4] * i[3]) /
                            a,
                        (-o[0] * i[1] +
                            o[1] * i[0] +
                            i[4] * i[1] -
                            i[5] * i[0]) /
                            a,
                    ]
                );
            }
            function U(t, e, n) {
                var r = [1, 0, 0, 1, 0, 0];
                r = g(
                    (r = b(r, (n = n || W(e).viewport).scale, n.scale)),
                    n.rotation,
                );
                var o,
                    i,
                    a = O(n);
                return (
                    (r = h(r, a.x, a.y)),
                    (i = r),
                    [
                        (o = t)[0] * i[0] + o[1] * i[2] + i[4],
                        o[0] * i[1] + o[1] * i[3] + i[5],
                    ]
                );
            }
            function Q(t, e) {
                var n = {},
                    r = W(t).viewport;
                return (
                    Object.keys(e).forEach(function (t) {
                        n[t] = e[t] / r.scale;
                    }),
                    n
                );
            }
            function H() {
                M.parentNode || document.head.appendChild(M);
            }
            function D() {
                M.parentNode && M.parentNode.removeChild(M);
            }
            function W(t) {
                return {
                    documentId: t.getAttribute("data-pdf-annotate-document"),
                    pageNumber: parseInt(
                        t.getAttribute("data-pdf-annotate-page"),
                        10,
                    ),
                    viewport: JSON.parse(
                        t.getAttribute("data-pdf-annotate-viewport"),
                    ),
                };
            }
            (M.innerHTML =
                "\nbody {\n  -webkit-user-select: none;\n  -moz-user-select: none;\n  -ms-user-select: none;\n  user-select: none;\n}\n"),
                M.setAttribute("data-pdf-annotate-user-select", "true");
            var $,
                J = new i.a();
            function V() {
                J.emit.apply(J, arguments);
            }
            function G() {
                J.on.apply(J, arguments);
            }
            function K() {
                J.removeListener.apply(J, arguments);
            }
            function Z(t, e) {
                if (!(t instanceof e))
                    throw new TypeError("Cannot call a class as a function");
            }
            function tt(t, e) {
                for (var n = 0; n < e.length; n++) {
                    var r = e[n];
                    (r.enumerable = r.enumerable || !1),
                        (r.configurable = !0),
                        "value" in r && (r.writable = !0),
                        Object.defineProperty(t, r.key, r);
                }
            }
            document.addEventListener("click", function (t) {
                if (B(t.clientX, t.clientY)) {
                    var e = q(t.clientX, t.clientY);
                    $ && $ !== e && J.emit("annotation:blur", $),
                        e && J.emit("annotation:click", e),
                        ($ = e);
                }
            });
            var et = (function () {
                    function t() {
                        var e = this,
                            n =
                                arguments.length > 0 && void 0 !== arguments[0]
                                    ? arguments[0]
                                    : {};
                        Z(this, t),
                            Object.keys(n).forEach(function (t) {
                                "function" == typeof n[t] &&
                                    "function" == typeof e[t] &&
                                    (e[t] = n[t]);
                            });
                    }
                    var e, n, o;
                    return (
                        (e = t),
                        (n = [
                            {
                                key: "__getAnnotations",
                                value: function (t, e) {
                                    r("getAnnotations");
                                },
                            },
                            {
                                key: "getAnnotations",
                                get: function () {
                                    return this.__getAnnotations;
                                },
                                set: function (t) {
                                    this.__getAnnotations = function (e, n) {
                                        return t
                                            .apply(void 0, arguments)
                                            .then(function (t) {
                                                return (
                                                    t.annotations &&
                                                        t.annotations.forEach(
                                                            function (t) {
                                                                t.documentId =
                                                                    e;
                                                            },
                                                        ),
                                                    t
                                                );
                                            });
                                    };
                                },
                            },
                            {
                                key: "getAnnotation",
                                value: function (t, e) {
                                    r("getAnnotation");
                                },
                            },
                            {
                                key: "__addAnnotation",
                                value: function (t, e, n) {
                                    r("addAnnotation");
                                },
                            },
                            {
                                key: "addAnnotation",
                                get: function () {
                                    return this.__addAnnotation;
                                },
                                set: function (t) {
                                    this.__addAnnotation = function (e, n, r) {
                                        return t
                                            .apply(void 0, arguments)
                                            .then(function (t) {
                                                return (
                                                    V(
                                                        "annotation:add",
                                                        e,
                                                        n,
                                                        t,
                                                    ),
                                                    t
                                                );
                                            });
                                    };
                                },
                            },
                            {
                                key: "__editAnnotation",
                                value: function (t, e, n) {
                                    r("editAnnotation");
                                },
                            },
                            {
                                key: "editAnnotation",
                                get: function () {
                                    return this.__editAnnotation;
                                },
                                set: function (t) {
                                    this.__editAnnotation = function (e, n, r) {
                                        return t
                                            .apply(void 0, arguments)
                                            .then(function (t) {
                                                return (
                                                    V(
                                                        "annotation:edit",
                                                        e,
                                                        n,
                                                        t,
                                                    ),
                                                    t
                                                );
                                            });
                                    };
                                },
                            },
                            {
                                key: "__deleteAnnotation",
                                value: function (t, e) {
                                    r("deleteAnnotation");
                                },
                            },
                            {
                                key: "deleteAnnotation",
                                get: function () {
                                    return this.__deleteAnnotation;
                                },
                                set: function (t) {
                                    this.__deleteAnnotation = function (e, n) {
                                        return t
                                            .apply(void 0, arguments)
                                            .then(function (t) {
                                                return (
                                                    t &&
                                                        V(
                                                            "annotation:delete",
                                                            e,
                                                            n,
                                                        ),
                                                    t
                                                );
                                            });
                                    };
                                },
                            },
                            {
                                key: "getComments",
                                value: function (t, e) {
                                    r("getComments");
                                },
                            },
                            {
                                key: "__addComment",
                                value: function (t, e, n) {
                                    r("addComment");
                                },
                            },
                            {
                                key: "addComment",
                                get: function () {
                                    return this.__addComment;
                                },
                                set: function (t) {
                                    this.__addComment = function (e, n, r) {
                                        return t
                                            .apply(void 0, arguments)
                                            .then(function (t) {
                                                return (
                                                    V("comment:add", e, n, t), t
                                                );
                                            });
                                    };
                                },
                            },
                            {
                                key: "__deleteComment",
                                value: function (t, e) {
                                    r("deleteComment");
                                },
                            },
                            {
                                key: "deleteComment",
                                get: function () {
                                    return this.__deleteComment;
                                },
                                set: function (t) {
                                    this.__deleteComment = function (e, n) {
                                        return t
                                            .apply(void 0, arguments)
                                            .then(function (t) {
                                                return (
                                                    t &&
                                                        V(
                                                            "comment:delete",
                                                            e,
                                                            n,
                                                        ),
                                                    t
                                                );
                                            });
                                    };
                                },
                            },
                        ]) && tt(e.prototype, n),
                        o && tt(e, o),
                        Object.defineProperty(e, "prototype", { writable: !1 }),
                        t
                    );
                })(),
                nt = /[xy]/g;
            function rt(t) {
                var e = (16 * Math.random()) | 0;
                return ("x" === t ? e : (3 & e) | 8).toString(16);
            }
            function ot() {
                return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(nt, rt);
            }
            function it(t) {
                return (it =
                    "function" == typeof Symbol &&
                    "symbol" == typeof Symbol.iterator
                        ? function (t) {
                              return typeof t;
                          }
                        : function (t) {
                              return t &&
                                  "function" == typeof Symbol &&
                                  t.constructor === Symbol &&
                                  t !== Symbol.prototype
                                  ? "symbol"
                                  : typeof t;
                          })(t);
            }
            function at(t, e) {
                for (var n = 0; n < e.length; n++) {
                    var r = e[n];
                    (r.enumerable = r.enumerable || !1),
                        (r.configurable = !0),
                        "value" in r && (r.writable = !0),
                        Object.defineProperty(t, r.key, r);
                }
            }
            function ut(t, e) {
                return (ut =
                    Object.setPrototypeOf ||
                    function (t, e) {
                        return (t.__proto__ = e), t;
                    })(t, e);
            }
            function ct(t) {
                var e = (function () {
                    if ("undefined" == typeof Reflect || !Reflect.construct)
                        return !1;
                    if (Reflect.construct.sham) return !1;
                    if ("function" == typeof Proxy) return !0;
                    try {
                        return (
                            Boolean.prototype.valueOf.call(
                                Reflect.construct(Boolean, [], function () {}),
                            ),
                            !0
                        );
                    } catch (t) {
                        return !1;
                    }
                })();
                return function () {
                    var n,
                        r = st(t);
                    if (e) {
                        var o = st(this).constructor;
                        n = Reflect.construct(r, arguments, o);
                    } else n = r.apply(this, arguments);
                    return lt(this, n);
                };
            }
            function lt(t, e) {
                if (e && ("object" === it(e) || "function" == typeof e))
                    return e;
                if (void 0 !== e)
                    throw new TypeError(
                        "Derived constructors may only return object or undefined",
                    );
                return (function (t) {
                    if (void 0 === t)
                        throw new ReferenceError(
                            "this hasn't been initialised - super() hasn't been called",
                        );
                    return t;
                })(t);
            }
            function st(t) {
                return (st = Object.setPrototypeOf
                    ? Object.getPrototypeOf
                    : function (t) {
                          return t.__proto__ || Object.getPrototypeOf(t);
                      })(t);
            }
            var dt = (function (t) {
                !(function (t, e) {
                    if ("function" != typeof e && null !== e)
                        throw new TypeError(
                            "Super expression must either be null or a function",
                        );
                    (t.prototype = Object.create(e && e.prototype, {
                        constructor: {
                            value: t,
                            writable: !0,
                            configurable: !0,
                        },
                    })),
                        Object.defineProperty(t, "prototype", { writable: !1 }),
                        e && ut(t, e);
                })(i, t);
                var e,
                    n,
                    r,
                    o = ct(i);
                function i() {
                    var t;
                    return (
                        (function (t, e) {
                            if (!(t instanceof e))
                                throw new TypeError(
                                    "Cannot call a class as a function",
                                );
                        })(this, i),
                        ((t = o.call(this, {
                            getAnnotations: function (t, e) {
                                return new Promise(function (n, r) {
                                    var o = ft(t).filter(function (t) {
                                        return (
                                            t.page === e &&
                                            "Annotation" === t.class
                                        );
                                    });
                                    n({
                                        documentId: t,
                                        pageNumber: e,
                                        annotations: o,
                                    });
                                });
                            },
                        })).getAnnotation = function (t, e) {
                            return Promise.resolve(ft(t)[yt(t, e)]);
                        }),
                        (t.addAnnotation = function (t, e, n) {
                            return new Promise(function (r, o) {
                                (n.class = "Annotation"),
                                    (n.uuid = ot()),
                                    (n.page = e);
                                var i = ft(t);
                                i.push(n), pt(t, i), r(n);
                            });
                        }),
                        (t.editAnnotation = function (t, e, n) {
                            return new Promise(function (r, o) {
                                var i = ft(t);
                                (i[yt(t, e)] = n), pt(t, i), r(n);
                            });
                        }),
                        (t.deleteAnnotation = function (t, e) {
                            return new Promise(function (n, r) {
                                if (
                                    !(
                                        ft(t).filter(function (t) {
                                            return t.uuid === e;
                                        })[0] || {}
                                    )
                                )
                                    return r("Could not find annotation");
                                var o = yt(t, e);
                                if (o > -1) {
                                    var i = ft(t);
                                    i.splice(o, 1), pt(t, i);
                                }
                                n(!0);
                            });
                        }),
                        (t.getComments = function (t, e) {
                            return new Promise(function (n, r) {
                                n(
                                    ft(t).filter(function (t) {
                                        return (
                                            "Comment" === t.class &&
                                            t.annotation === e
                                        );
                                    }),
                                );
                            });
                        }),
                        (t.addComment = function (t, e, n) {
                            return new Promise(function (r, o) {
                                var i = {
                                        class: "Comment",
                                        uuid: ot(),
                                        annotation: e,
                                        content: n,
                                    },
                                    a = ft(t);
                                a.push(i), pt(t, a), r(i);
                            });
                        }),
                        (t.deleteComment = function (t, e) {
                            return new Promise(function (n, r) {
                                if (
                                    !(
                                        ft(t).filter(function (t) {
                                            return t.uuid === e;
                                        })[0] || {}
                                    )
                                )
                                    return r("Could not find annotation");
                                for (
                                    var o = -1, i = ft(t), a = 0, u = i.length;
                                    a < u;
                                    a++
                                )
                                    if (i[a].uuid === e) {
                                        o = a;
                                        break;
                                    }
                                o > -1 && (i.splice(o, 1), pt(t, i)), n(!0);
                            });
                        }),
                        t
                    );
                }
                return (
                    (e = i),
                    n && at(e.prototype, n),
                    r && at(e, r),
                    Object.defineProperty(e, "prototype", { writable: !1 }),
                    e
                );
            })(et);
            function ft(t) {
                return (
                    JSON.parse(
                        localStorage.getItem("".concat(t, "/annotations")),
                    ) || []
                );
            }
            function pt(t, e) {
                localStorage.setItem(
                    "".concat(t, "/annotations"),
                    JSON.stringify(e),
                );
            }
            function yt(t, e) {
                for (var n = -1, r = ft(t), o = 0, i = r.length; o < i; o++)
                    if (r[o].uuid === e) {
                        n = o;
                        break;
                    }
                return n;
            }
            function mt(t) {
                return (mt =
                    "function" == typeof Symbol &&
                    "symbol" == typeof Symbol.iterator
                        ? function (t) {
                              return typeof t;
                          }
                        : function (t) {
                              return t &&
                                  "function" == typeof Symbol &&
                                  t.constructor === Symbol &&
                                  t !== Symbol.prototype
                                  ? "symbol"
                                  : typeof t;
                          })(t);
            }
            function vt(t) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return ht(t);
                    })(t) ||
                    (function (t) {
                        if (
                            ("undefined" != typeof Symbol &&
                                null != t[Symbol.iterator]) ||
                            null != t["@@iterator"]
                        )
                            return Array.from(t);
                    })(t) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return ht(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return ht(t, e);
                    })(t) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function ht(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            function gt(t, e) {
                if (!(t instanceof e))
                    throw new TypeError("Cannot call a class as a function");
            }
            function bt(t, e) {
                for (var n = 0; n < e.length; n++) {
                    var r = e[n];
                    (r.enumerable = r.enumerable || !1),
                        (r.configurable = !0),
                        "value" in r && (r.writable = !0),
                        Object.defineProperty(t, r.key, r);
                }
            }
            function wt(t, e) {
                return (wt =
                    Object.setPrototypeOf ||
                    function (t, e) {
                        return (t.__proto__ = e), t;
                    })(t, e);
            }
            function xt(t) {
                var e = (function () {
                    if ("undefined" == typeof Reflect || !Reflect.construct)
                        return !1;
                    if (Reflect.construct.sham) return !1;
                    if ("function" == typeof Proxy) return !0;
                    try {
                        return (
                            Boolean.prototype.valueOf.call(
                                Reflect.construct(Boolean, [], function () {}),
                            ),
                            !0
                        );
                    } catch (t) {
                        return !1;
                    }
                })();
                return function () {
                    var n,
                        r = St(t);
                    if (e) {
                        var o = St(this).constructor;
                        n = Reflect.construct(r, arguments, o);
                    } else n = r.apply(this, arguments);
                    return At(this, n);
                };
            }
            function At(t, e) {
                if (e && ("object" === mt(e) || "function" == typeof e))
                    return e;
                if (void 0 !== e)
                    throw new TypeError(
                        "Derived constructors may only return object or undefined",
                    );
                return (function (t) {
                    if (void 0 === t)
                        throw new ReferenceError(
                            "this hasn't been initialised - super() hasn't been called",
                        );
                    return t;
                })(t);
            }
            function St(t) {
                return (St = Object.setPrototypeOf
                    ? Object.getPrototypeOf
                    : function (t) {
                          return t.__proto__ || Object.getPrototypeOf(t);
                      })(t);
            }
            var Et = (function (t) {
                !(function (t, e) {
                    if ("function" != typeof e && null !== e)
                        throw new TypeError(
                            "Super expression must either be null or a function",
                        );
                    (t.prototype = Object.create(e && e.prototype, {
                        constructor: {
                            value: t,
                            writable: !0,
                            configurable: !0,
                        },
                    })),
                        Object.defineProperty(t, "prototype", { writable: !1 }),
                        e && wt(t, e);
                })(i, t);
                var e,
                    n,
                    r,
                    o = xt(i);
                function i() {
                    var t,
                        e =
                            arguments.length > 0 && void 0 !== arguments[0]
                                ? arguments[0]
                                : "user",
                        n =
                            arguments.length > 1 &&
                            void 0 !== arguments[1] &&
                            arguments[1];
                    return (
                        gt(this, i),
                        ((t = o.call(this, {
                            getAnnotations: function (t, e) {
                                return new Promise(function (n, r) {
                                    var o = Ct(t).filter(function (t) {
                                        return (
                                            t.page === e &&
                                            "Annotation" === t.class
                                        );
                                    });
                                    n({
                                        documentId: t,
                                        pageNumber: e,
                                        annotations: o,
                                    });
                                });
                            },
                        }))._userId = e),
                        (t._globalEdit = n),
                        (t.getAnnotation = function (e, n) {
                            return Promise.resolve(
                                Lt(e, t._userId)[_t(e, t._userId, n)],
                            );
                        }),
                        (t.addAnnotation = function (e, n, r) {
                            return new Promise(function (o, i) {
                                (r.class = "Annotation"),
                                    (r.uuid = ot()),
                                    (r.page = n),
                                    (r.userId = t._userId);
                                var a = Lt(e, t._userId);
                                a.push(r), It(e, t._userId, a), o(r);
                            });
                        }),
                        (t.editAnnotation = function (e, n, r) {
                            return new Promise(function (o, i) {
                                !t._globalEdit &&
                                    r.userId &&
                                    r.userId !== t._userId &&
                                    i("Non-matching userId");
                                var a = Lt(e, r.userId);
                                (a[_t(e, r.userId, n)] = r),
                                    It(e, r.userId, a),
                                    o(r);
                            });
                        }),
                        (t.deleteAnnotation = function (e, n) {
                            return new Promise(function (r, o) {
                                var i =
                                    Ct(e).filter(function (t) {
                                        return t.uuid === n;
                                    })[0] || {};
                                if (!i) return o("Could not find annotation");
                                if (
                                    !t._globalEdit &&
                                    i.userId &&
                                    i.userId !== t._userId
                                )
                                    return o("Non-matching userId");
                                var a = _t(e, i.userId, n);
                                if (a > -1) {
                                    var u = Lt(e, i.userId);
                                    u.splice(a, 1), It(e, i.userId, u);
                                }
                                r(!0);
                            });
                        }),
                        (t.getComments = function (e, n) {
                            return new Promise(function (r, o) {
                                r(
                                    Lt(e, t._userId).filter(function (t) {
                                        return (
                                            "Comment" === t.class &&
                                            t.annotation === n
                                        );
                                    }),
                                );
                            });
                        }),
                        (t.addComment = function (e, n, r) {
                            return new Promise(function (o, i) {
                                var a = {
                                        class: "Comment",
                                        uuid: ot(),
                                        annotation: n,
                                        content: r,
                                        userId: t._userId,
                                    },
                                    u = Lt(e, t._userId);
                                u.push(a), It(e, t._userId, u), o(a);
                            });
                        }),
                        (t.deleteComment = function (e, n) {
                            return new Promise(function (r, o) {
                                var i =
                                    Ct(e).filter(function (t) {
                                        return t.uuid === n;
                                    })[0] || {};
                                if (!i) return o("Could not find annotation");
                                if (
                                    !t._globalEdit &&
                                    i.userId &&
                                    i.userId !== t._userId
                                )
                                    return o("Non-matching userId");
                                for (
                                    var a = -1,
                                        u = Lt(e, i.userId),
                                        c = 0,
                                        l = u.length;
                                    c < l;
                                    c++
                                )
                                    if (u[c].uuid === n) {
                                        a = c;
                                        break;
                                    }
                                a > -1 && (u.splice(a, 1), It(e, i.userId, u)),
                                    r(!0);
                            });
                        }),
                        t
                    );
                }
                return (
                    (e = i),
                    (n = [
                        {
                            key: "userId",
                            get: function () {
                                return this._userId;
                            },
                        },
                    ]) && bt(e.prototype, n),
                    r && bt(e, r),
                    Object.defineProperty(e, "prototype", { writable: !1 }),
                    i
                );
            })(et);
            function Ct(t) {
                for (
                    var e = [],
                        n = new RegExp("".concat(t, "/(.*)/annotations")),
                        r = 0;
                    r < localStorage.length;
                    r++
                )
                    localStorage.key(r).search(n) > -1 &&
                        e.push.apply(
                            e,
                            vt(
                                JSON.parse(
                                    localStorage.getItem(localStorage.key(r)),
                                ),
                            ),
                        );
                return e;
            }
            function Lt(t, e) {
                return (
                    JSON.parse(
                        localStorage.getItem(
                            "".concat(t, "/").concat(e, "/annotations"),
                        ),
                    ) || []
                );
            }
            function It(t, e, n) {
                localStorage.setItem(
                    "".concat(t, "/").concat(e, "/annotations"),
                    JSON.stringify(n),
                );
            }
            function _t(t, e, n) {
                for (var r = -1, o = Lt(t, e), i = 0, a = o.length; i < a; i++)
                    if (o[i].uuid === n) {
                        r = i;
                        break;
                    }
                return r;
            }
            var kt = {
                annotationLayerName: "annotationLayer",
                textLayerName: "textLayer",
                annotationSvgQuery: function () {
                    return "svg." + this.annotationLayerName;
                },
                annotationClassQuery: function () {
                    return "." + this.annotationLayerName;
                },
                textClassQuery: function () {
                    return "." + this.textLayerName;
                },
            };
            function Ot(t) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return Nt(t);
                    })(t) ||
                    (function (t) {
                        if (
                            ("undefined" != typeof Symbol &&
                                null != t[Symbol.iterator]) ||
                            null != t["@@iterator"]
                        )
                            return Array.from(t);
                    })(t) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return Nt(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return Nt(t, e);
                    })(t) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function Nt(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            var jt,
                Pt,
                Rt,
                Mt,
                Tt,
                Bt = !1,
                qt = !1;
            function zt() {
                jt && (jt.parentNode.removeChild(jt), (jt = null)),
                    document.removeEventListener("click", Xt),
                    document.removeEventListener("keyup", Yt),
                    document.removeEventListener("mousedown", Ut),
                    document.removeEventListener("mousemove", Qt),
                    document.removeEventListener("mouseup", Ht),
                    D();
            }
            function Ft() {
                if (jt) {
                    var t = jt.getAttribute("data-target-id"),
                        e = W(
                            jt.parentNode.querySelector(
                                kt.annotationSvgQuery(),
                            ),
                        ).documentId;
                    In.getStoreAdapter()
                        .deleteAnnotation(e, t)
                        .then(function () {
                            Ot(
                                document.querySelectorAll(
                                    '[data-pdf-annotate-id="'.concat(t, '"]'),
                                ),
                            ).forEach(function (t) {
                                t.parentNode.removeChild(t);
                            });
                        }),
                        zt();
                }
            }
            function Xt(t) {
                if (B(t.clientX, t.clientY)) {
                    var e = document.getElementById(
                        "pdf-annotate-edit-overlay",
                    );
                    if (e) {
                        if (qt || t.target === e) return;
                        zt();
                    }
                }
            }
            function Yt(t) {
                var e;
                (e = t.key
                    ? "delete" === t.key.toLowerCase() ||
                      "backspace" === t.key.toLowerCase()
                    : 8 === t.keyCode || 46 === t.keyCode),
                    jt &&
                        e &&
                        "textarea" !== t.target.nodeName.toLowerCase() &&
                        "input" !== t.target.nodeName.toLowerCase() &&
                        (t.preventDefault(), Ft());
            }
            function Ut(t) {
                if (t.target === jt) {
                    var e = jt.getAttribute("data-target-id"),
                        n = document
                            .querySelector(
                                '[data-pdf-annotate-id="'.concat(e, '"]'),
                            )
                            .getAttribute("data-pdf-annotate-type");
                    "highlight" !== n &&
                        "strikeout" !== n &&
                        ((qt = !0),
                        (Pt = t.clientX),
                        (Rt = t.clientY),
                        (Mt = jt.offsetLeft),
                        (Tt = jt.offsetTop),
                        (jt.style.background = "rgba(255, 255, 255, 0.7)"),
                        (jt.style.cursor = "move"),
                        (jt.querySelector("a").style.display = "none"),
                        document.addEventListener("mousemove", Qt),
                        document.addEventListener("mouseup", Ht),
                        H());
                }
            }
            function Qt(t) {
                var e = jt.parentNode.getBoundingClientRect(),
                    n = Tt + (t.clientY - Rt),
                    r = Mt + (t.clientX - Pt),
                    o = e.height,
                    i = e.width;
                n > 0 &&
                    n + jt.offsetHeight < o &&
                    (jt.style.top = "".concat(n, "px")),
                    r > 0 &&
                        r + jt.offsetWidth < i &&
                        (jt.style.left = "".concat(r, "px"));
            }
            function Ht(t) {
                var e = jt.getAttribute("data-target-id"),
                    n = document.querySelectorAll(
                        '[data-pdf-annotate-id="'.concat(e, '"]'),
                    ),
                    r = n[0].getAttribute("data-pdf-annotate-type"),
                    o = jt.parentNode.querySelector(kt.annotationSvgQuery()),
                    i = W(o).documentId;
                (jt.querySelector("a").style.display = ""),
                    In.getStoreAdapter()
                        .getAnnotation(i, e)
                        .then(function (t) {
                            var a = "x",
                                u = "y";
                            if (
                                ([
                                    "circle",
                                    "fillcircle",
                                    "emptycircle",
                                ].indexOf(r) > -1 && ((a = "cx"), (u = "cy")),
                                "point" !== r)
                            ) {
                                if (
                                    [
                                        "area",
                                        "highlight",
                                        "textbox",
                                        "circle",
                                        "fillcircle",
                                        "emptycircle",
                                    ].indexOf(r) > -1
                                ) {
                                    var c = Y([Mt, Tt], o),
                                        l = Y([jt.offsetLeft, jt.offsetTop], o),
                                        s = { x: l[0] - c[0], y: l[1] - c[1] };
                                    "textbox" === r && (n = [n[0].firstChild]),
                                        Ot(n).forEach(function (e, n) {
                                            var r = parseInt(
                                                    e.getAttribute(a),
                                                    10,
                                                ),
                                                o = parseInt(
                                                    e.getAttribute(u),
                                                    10,
                                                );
                                            0 !== s.y &&
                                                ((o += s.y),
                                                e.setAttribute(u, o),
                                                t.rectangles &&
                                                n < t.rectangles.length
                                                    ? (t.rectangles[n].y = o)
                                                    : t[u] && (t[u] = o)),
                                                0 !== s.x &&
                                                    ((r += s.x),
                                                    e.setAttribute(a, r),
                                                    t.rectangles &&
                                                    n < t.rectangles.length
                                                        ? (t.rectangles[n].x =
                                                              r)
                                                        : t[a] && (t[a] = r));
                                        });
                                } else {
                                    if ("strikeout" === r) return;
                                    if ("drawing" === r || "arrow" === r)
                                        return;
                                }
                                In.getStoreAdapter().editAnnotation(i, e, t);
                            }
                        }),
                    setTimeout(function () {
                        qt = !1;
                    }, 0),
                    (jt.style.background = ""),
                    (jt.style.cursor = ""),
                    document.removeEventListener("mousemove", Qt),
                    document.removeEventListener("mouseup", Ht),
                    D();
            }
            function Dt(t) {
                !(function (t) {
                    zt(), (jt = document.createElement("div"));
                    var e = document.createElement("a"),
                        n = T(t).parentNode,
                        r = t.getAttribute("data-pdf-annotate-id"),
                        o = F(t),
                        i = o.left - 3,
                        a = o.top - 3;
                    jt.setAttribute("id", "pdf-annotate-edit-overlay"),
                        jt.setAttribute("data-target-id", r),
                        (jt.style.boxSizing = "content-box"),
                        (jt.style.position = "absolute"),
                        (jt.style.top = "".concat(a, "px")),
                        (jt.style.left = "".concat(i, "px")),
                        (jt.style.width = "".concat(o.width, "px")),
                        (jt.style.height = "".concat(o.height, "px")),
                        (jt.style.border = "".concat(3, "px solid ").concat(R)),
                        (jt.style.borderRadius = "".concat(3, "px")),
                        (jt.style.zIndex = 20100),
                        (e.innerHTML = "×"),
                        e.setAttribute("href", "javascript://"),
                        (e.style.background = "#fff"),
                        (e.style.borderRadius = "20px"),
                        (e.style.border = "1px solid #bbb"),
                        (e.style.color = "#bbb"),
                        (e.style.fontSize = "16px"),
                        (e.style.padding = "2px"),
                        (e.style.textAlign = "center"),
                        (e.style.textDecoration = "none"),
                        (e.style.position = "absolute"),
                        (e.style.top = "-13px"),
                        (e.style.right = "-13px"),
                        (e.style.width = "25px"),
                        (e.style.height = "25px"),
                        jt.appendChild(e),
                        n.appendChild(jt),
                        document.addEventListener("click", Xt),
                        document.addEventListener("keyup", Yt),
                        document.addEventListener("mousedown", Ut),
                        e.addEventListener("click", Ft),
                        e.addEventListener("mouseover", function () {
                            (e.style.color = "#35A4DC"),
                                (e.style.borderColor = "#999"),
                                (e.style.boxShadow = "0 1px 1px #ccc");
                        }),
                        e.addEventListener("mouseout", function () {
                            (e.style.color = "#bbb"),
                                (e.style.borderColor = "#bbb"),
                                (e.style.boxShadow = "");
                        }),
                        jt.addEventListener("mouseover", function () {
                            qt || (e.style.display = "");
                        }),
                        jt.addEventListener("mouseout", function () {
                            e.style.display = "none";
                        });
                })(t);
            }
            var Wt,
                $t,
                Jt,
                Vt = !1,
                Gt = !1,
                Kt = [];
            function Zt(t) {
                (Jt = null),
                    (Kt = []),
                    B(t.clientX, t.clientY) && ((Gt = !0), t.preventDefault());
            }
            function te(t) {
                !(function (t, e) {
                    if (!Gt) return;
                    var n;
                    if (((Gt = !1), Kt.length > 1 && (n = B(t, e)))) {
                        var r = W(n),
                            o = r.documentId,
                            i = r.pageNumber;
                        In.getStoreAdapter()
                            .addAnnotation(o, i, {
                                type: "drawing",
                                width: Wt,
                                color: $t,
                                lines: Kt,
                            })
                            .then(function (t) {
                                Jt && n.removeChild(Jt), j(n, t);
                            });
                    }
                })(t.clientX, t.clientY);
            }
            function ee(t) {
                B(t.clientX, t.clientY) &&
                    Gt &&
                    (function (t, e) {
                        var n = B(t, e);
                        if (!n) return;
                        var r = n.getBoundingClientRect(),
                            o = Y([t - r.left, e - r.top], n);
                        if (
                            ((o[0] = o[0].toFixed(2)),
                            (o[1] = o[1].toFixed(2)),
                            Kt.push(o),
                            Kt.length <= 1)
                        )
                            return;
                        Jt && n.removeChild(Jt);
                        Jt = j(n, {
                            type: "drawing",
                            color: $t,
                            width: Wt,
                            lines: Kt,
                        });
                    })(t.clientX, t.clientY);
            }
            function ne(t) {
                27 === t.keyCode &&
                    ((Kt = null),
                    Jt.parentNode.removeChild(Jt),
                    document.removeEventListener("pointermove", ee),
                    document.removeEventListener("pointerup", te));
            }
            var re,
                oe,
                ie,
                ae,
                ue,
                ce,
                le = !1;
            function se(t) {
                var e = q(t.clientX, t.clientY);
                if (null !== e) {
                    var n = e.getAttribute("data-pdf-annotate-type");
                    if (
                        "circle" === n ||
                        "fillcircle" === n ||
                        "emptycircle" === n
                    ) {
                        var r = T(e),
                            o = W(r).documentId,
                            i = e.getAttribute("data-pdf-annotate-id");
                        In.getStoreAdapter()
                            .getAnnotation(o, i)
                            .then(function (t) {
                                if (t) {
                                    (ie = null), (ae = []);
                                    var e = U([t.cx, t.cy], r),
                                        n = r.getBoundingClientRect();
                                    (ce = e[0] + n.left),
                                        (ue = e[1] + n.top),
                                        document.addEventListener(
                                            "mousemove",
                                            fe,
                                        ),
                                        document.addEventListener(
                                            "mouseup",
                                            de,
                                        );
                                }
                            });
                    }
                }
            }
            function de(t) {
                var e;
                if (ae.length > 1 && (e = B(t.clientX, t.clientY))) {
                    var n = W(e),
                        r = n.documentId,
                        o = n.pageNumber;
                    In.getStoreAdapter()
                        .addAnnotation(r, o, {
                            type: "arrow",
                            width: re,
                            color: oe,
                            lines: ae,
                        })
                        .then(function (t) {
                            ie && e.removeChild(ie), j(e, t);
                        });
                }
                document.removeEventListener("mousemove", fe),
                    document.removeEventListener("mouseup", de);
            }
            function fe(t) {
                !(function (t, e) {
                    var n = B(t, e);
                    if (!n) return;
                    var r = n.getBoundingClientRect(),
                        o = Y([t - r.left, e - r.top], n);
                    if (ae.length < 2) return void ae.push(o);
                    ae[1] = o;
                    ie && n.removeChild(ie);
                    ie = j(n, {
                        type: "arrow",
                        color: oe,
                        width: re,
                        lines: ae,
                    });
                })(
                    0 === ae.length ? ce : t.clientX,
                    0 === ae.length ? ue : t.clientY,
                );
            }
            function pe(t) {
                27 === t.keyCode &&
                    ((ae = null),
                    ie.parentNode.removeChild(ie),
                    document.removeEventListener("mousemove", fe),
                    document.removeEventListener("mouseup", de));
            }
            function ye(t) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return me(t);
                    })(t) ||
                    (function (t) {
                        if (
                            ("undefined" != typeof Symbol &&
                                null != t[Symbol.iterator]) ||
                            null != t["@@iterator"]
                        )
                            return Array.from(t);
                    })(t) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return me(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return me(t, e);
                    })(t) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function me(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            var ve = !1,
                he = null;
            function ge(t) {
                (ve = !0), (he = [t.clientX, t.clientY]);
            }
            function be(t) {
                (ve = !1), xe(q(t.clientX, t.clientY));
            }
            function we(t) {
                if (ve) {
                    var e = [],
                        n = Math.abs(he[0] - t.clientX),
                        r = Math.abs(he[1] - t.clientY);
                    if (n >= 1 || r >= 1)
                        for (
                            var o = Math.round(Math.max(n, r)),
                                i = Math.min(n, r) / o,
                                a = n < r,
                                u = [
                                    Math.min(he[0], t.clientX),
                                    Math.min(he[1], t.clientY),
                                ],
                                c = 0;
                            c < o;
                            c++
                        )
                            a
                                ? e.push([
                                      Math.round(u[0] + i * c),
                                      Math.round(u[1] + c),
                                  ])
                                : e.push([
                                      Math.round(u[0] + c),
                                      Math.round(u[1] + i * c),
                                  ]);
                    for (var l = 0, s = e; l < s.length; l++) {
                        var d = s[l];
                        xe(q(d[0], d[1]));
                    }
                    he = [t.clientX, t.clientY];
                }
            }
            function xe(t) {
                if (ve && t) {
                    var e = W(t.parentElement).documentId,
                        n = t.getAttribute("data-pdf-annotate-id");
                    In.getStoreAdapter()
                        .deleteAnnotation(e, n)
                        .then(function () {
                            ye(
                                document.querySelectorAll(
                                    '[data-pdf-annotate-id="'.concat(n, '"]'),
                                ),
                            ).forEach(function (t) {
                                t.parentNode.removeChild(t);
                            });
                        });
                }
            }
            var Ae,
                Se = !1;
            function Ee(t) {
                !Ae &&
                    B(t.clientX, t.clientY) &&
                    ((Ae = document.createElement("input")).setAttribute(
                        "id",
                        "pdf-annotate-point-input",
                    ),
                    Ae.setAttribute("placeholder", "Enter comment"),
                    (Ae.style.border = "3px solid ".concat(R)),
                    (Ae.style.borderRadius = "3px"),
                    (Ae.style.position = "absolute"),
                    (Ae.style.top = "".concat(t.clientY, "px")),
                    (Ae.style.left = "".concat(t.clientX, "px")),
                    Ae.addEventListener("blur", Ce),
                    Ae.addEventListener("keyup", Le),
                    document.body.appendChild(Ae),
                    Ae.focus());
            }
            function Ce() {
                Ie();
            }
            function Le(t) {
                27 === t.keyCode ? _e() : 13 === t.keyCode && Ie();
            }
            function Ie() {
                if (Ae.value.trim().length > 0) {
                    var t = parseInt(Ae.style.left, 10),
                        e = parseInt(Ae.style.top, 10),
                        n = Ae.value.trim(),
                        r = B(t, e);
                    if (!r) return;
                    var o = r.getBoundingClientRect(),
                        i = W(r),
                        a = i.documentId,
                        u = i.pageNumber,
                        c = Object.assign(
                            { type: "point" },
                            Q(r, { x: t - o.left, y: e - o.top }),
                        );
                    In.getStoreAdapter()
                        .addAnnotation(a, u, c)
                        .then(function (t) {
                            In.getStoreAdapter().addComment(a, t.uuid, n),
                                j(r, t);
                        });
                }
                _e();
            }
            function _e() {
                Ae.removeEventListener("blur", Ce),
                    Ae.removeEventListener("keyup", Le),
                    document.body.removeChild(Ae),
                    (Ae = null);
            }
            function ke(t) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return Oe(t);
                    })(t) ||
                    (function (t) {
                        if (
                            ("undefined" != typeof Symbol &&
                                null != t[Symbol.iterator]) ||
                            null != t["@@iterator"]
                        )
                            return Array.from(t);
                    })(t) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return Oe(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return Oe(t, e);
                    })(t) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function Oe(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            var Ne,
                je,
                Pe,
                Re,
                Me = !1;
            function Te(t) {
                var e;
                if ("area" === Ne && (e = B(t.clientX, t.clientY))) {
                    var n = e.getBoundingClientRect();
                    (Pe = t.clientY),
                        (Re = t.clientX),
                        ((je = document.createElement("div")).style.position =
                            "absolute"),
                        (je.style.top = "".concat(Pe - n.top, "px")),
                        (je.style.left = "".concat(Re - n.left, "px")),
                        (je.style.border = "3px solid ".concat(R)),
                        (je.style.borderRadius = "3px"),
                        e.parentNode.appendChild(je),
                        document.addEventListener("mousemove", Be),
                        H();
                }
            }
            function Be(t) {
                var e = je.parentNode
                    .querySelector(kt.annotationSvgQuery())
                    .getBoundingClientRect();
                Re + (t.clientX - Re) < e.right &&
                    (je.style.width = "".concat(t.clientX - Re, "px")),
                    Pe + (t.clientY - Pe) < e.bottom &&
                        (je.style.height = "".concat(t.clientY - Pe, "px"));
            }
            function qe(t) {
                var e;
                if (
                    "area" !== Ne &&
                    (e = (function () {
                        try {
                            var t = window
                                .getSelection()
                                .getRangeAt(0)
                                .getClientRects();
                            if (
                                t.length > 0 &&
                                t[0].width > 0 &&
                                t[0].height > 0
                            )
                                return t;
                        } catch (t) {}
                        return null;
                    })())
                )
                    Fe(
                        Ne,
                        ke(e).map(function (t) {
                            return {
                                top: t.top,
                                left: t.left,
                                width: t.width,
                                height: t.height,
                            };
                        }),
                    );
                else if ("area" === Ne && je) {
                    var n = je.parentNode
                        .querySelector(kt.annotationSvgQuery())
                        .getBoundingClientRect();
                    Fe(Ne, [
                        {
                            top: parseInt(je.style.top, 10) + n.top,
                            left: parseInt(je.style.left, 10) + n.left,
                            width: parseInt(je.style.width, 10),
                            height: parseInt(je.style.height, 10),
                        },
                    ]),
                        je.parentNode.removeChild(je),
                        (je = null),
                        document.removeEventListener("mousemove", Be),
                        D();
                }
            }
            function ze(t) {
                27 === t.keyCode &&
                    (window.getSelection().removeAllRanges(),
                    je &&
                        je.parentNode &&
                        (je.parentNode.removeChild(je),
                        (je = null),
                        document.removeEventListener("mousemove", Be)));
            }
            function Fe(t, e, n) {
                var r,
                    o = B(e[0].left, e[0].top);
                if (o) {
                    var i = o.getBoundingClientRect();
                    if (
                        (n ||
                            ("highlight" === t
                                ? (n = "FFFF00")
                                : "strikeout" === t && (n = "FF0000")),
                        0 !==
                            (r = {
                                type: t,
                                color: n,
                                rectangles: ke(e)
                                    .map(function (e) {
                                        var n = 0;
                                        return (
                                            "strikeout" === t &&
                                                (n = e.height / 2),
                                            (function (t, e, n) {
                                                var r = [t.x, t.y],
                                                    o = [
                                                        t.x + t.width,
                                                        t.y + t.height,
                                                    ];
                                                return (
                                                    (r = Y(r, e, n)),
                                                    (o = Y(o, e, n)),
                                                    {
                                                        x: Math.min(r[0], o[0]),
                                                        y: Math.min(r[1], o[1]),
                                                        width: Math.abs(
                                                            o[0] - r[0],
                                                        ),
                                                        height: Math.abs(
                                                            o[1] - r[1],
                                                        ),
                                                    }
                                                );
                                            })(
                                                {
                                                    y: e.top + n - i.top,
                                                    x: e.left - i.left,
                                                    width: e.width,
                                                    height: e.height,
                                                },
                                                o,
                                            )
                                        );
                                    })
                                    .filter(function (t) {
                                        return (
                                            t.width > 0 &&
                                            t.height > 0 &&
                                            t.x > -1 &&
                                            t.y > -1
                                        );
                                    }),
                            }).rectangles.length)
                    ) {
                        if ("area" === t) {
                            var a = r.rectangles[0];
                            delete r.rectangles,
                                (r.x = a.x),
                                (r.y = a.y),
                                (r.width = a.width),
                                (r.height = a.height);
                        }
                        var u = W(o),
                            c = u.documentId,
                            l = u.pageNumber;
                        In.getStoreAdapter()
                            .addAnnotation(c, l, r)
                            .then(function (t) {
                                j(o, t);
                            });
                    }
                }
            }
            var Xe,
                Ye = !1,
                Ue = 10,
                Qe = "0000FF";
            function He(t) {
                var e = B(t.clientX, t.clientY);
                if (e) {
                    var n = e.getBoundingClientRect();
                    !(function (t, e, n, r, o) {
                        var i = Y([n.x, n.y], t),
                            a = { type: e, color: o, cx: i[0], cy: i[1], r: r },
                            u = W(t),
                            c = u.documentId,
                            l = u.pageNumber;
                        In.getStoreAdapter()
                            .addAnnotation(c, l, a)
                            .then(function (e) {
                                j(t, e);
                            });
                    })(
                        e,
                        Xe,
                        { x: t.clientX - n.left, y: t.clientY - n.top },
                        Ue,
                        Qe,
                    );
                }
            }
            var De,
                We = !1,
                $e = localStorage.getItem("text/size") || 12,
                Je = localStorage.getItem("text/color") || "#000000";
            function Ve(t) {
                !De &&
                    B(t.clientX, t.clientY) &&
                    t.srcElement.classList.contains("annotationLayer") &&
                    ((De = document.createElement("textarea")).setAttribute(
                        "id",
                        "pdf-annotate-text-input",
                    ),
                    De.setAttribute(
                        "placeholder",
                        "Enter text... SHIFT + ENTER for new line",
                    ),
                    (De.style.border = "3px solid ".concat(R)),
                    (De.style.borderRadius = "3px"),
                    (De.style.position = "fixed"),
                    (De.style.top = "".concat(t.clientY, "px")),
                    (De.style.left = "".concat(t.clientX, "px")),
                    (De.style.fontSize = "".concat($e, "px")),
                    (De.style.zIndex = "41"),
                    De.addEventListener("blur", Ge),
                    De.addEventListener("keyup", Ke),
                    document.body.appendChild(De),
                    De.focus());
            }
            function Ge() {
                Ze();
            }
            function Ke(t) {
                27 === t.keyCode
                    ? tn()
                    : 13 !== t.keyCode || t.shiftKey || Ze();
            }
            function Ze() {
                var t = De.value ? De.value.replace(/ +$/, "") : "";
                if (t.length > 0) {
                    var e = parseInt(De.style.left, 10),
                        n = parseInt(De.style.top, 10),
                        r = B(e, n);
                    if (!r) return;
                    var o = $e,
                        i = W(r),
                        a = i.documentId,
                        u = i.pageNumber,
                        c = i.viewport,
                        l = 1 / c.scale,
                        s = r.getBoundingClientRect(),
                        d = Y([e - s.left, n - s.top + o], r, c),
                        f = {
                            type: "textbox",
                            size: $e * l,
                            color: Je,
                            content: t,
                            x: d[0],
                            y: d[1],
                            rotation: -c.rotation,
                        };
                    In.getStoreAdapter()
                        .addAnnotation(a, u, f)
                        .then(function (t) {
                            j(r, t);
                        });
                }
                tn();
            }
            function tn() {
                De &&
                    (De.removeEventListener("blur", Ge),
                    De.removeEventListener("keyup", Ke),
                    document.body.removeChild(De),
                    (De = null));
            }
            function en(t, e) {
                var n = document.createElement("div"),
                    r = document.createTextNode(t);
                return (
                    n.appendChild(r),
                    n.setAttribute(
                        "id",
                        "pdf-annotate-screenreader-".concat(e),
                    ),
                    (n.style.position = "absolute"),
                    (n.style.left = "-10000px"),
                    (n.style.top = "auto"),
                    (n.style.width = "1px"),
                    (n.style.height = "1px"),
                    (n.style.overflow = "hidden"),
                    n
                );
            }
            function nn(t) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return rn(t);
                    })(t) ||
                    (function (t) {
                        if (
                            ("undefined" != typeof Symbol &&
                                null != t[Symbol.iterator]) ||
                            null != t["@@iterator"]
                        )
                            return Array.from(t);
                    })(t) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return rn(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return rn(t, e);
                    })(t) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function rn(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            function on(t, e, n, r, o) {
                var i = (function (t, e, n) {
                    var r = document.querySelector(
                            'svg[data-pdf-annotate-page="'.concat(n, '"]'),
                        ),
                        o = r.getBoundingClientRect();
                    return (
                        (e = X(r, { y: e }).y + o.top),
                        (t = X(r, { x: t }).x + o.left),
                        nn(
                            r.parentNode.querySelectorAll(
                                kt.textClassQuery() + " [data-canvas-width]",
                            ),
                        ).filter(function (n) {
                            return z(t, e, n.getBoundingClientRect());
                        })[0]
                    );
                })((e = Math.max(e + 2 * (o ? 1 : -1), 0)), n + 2, r);
                if (!i) return !1;
                e += 2 * (o ? -1 : 1);
                var a = document.querySelector(
                        'svg[data-pdf-annotate-page="'.concat(r, '"]'),
                    ),
                    u =
                        Q(a, { left: i.getBoundingClientRect().left }).left -
                        a.getBoundingClientRect().left,
                    c = i.cloneNode(!0),
                    l = c.innerHTML.split(""),
                    s = [];
                for (
                    c.style.position = "absolute",
                        c.style.top = "-10000px",
                        c.style.left = "-10000px",
                        document.body.appendChild(c);
                    l.length;

                ) {
                    if (">" === l[l.length - 1])
                        for (
                            ;
                            l.length && (s.unshift(l.pop()), "<" !== s[0]);

                        );
                    if (
                        ((c.innerHTML = l.join("")),
                        u +
                            Q(a, { width: c.getBoundingClientRect().width })
                                .width <=
                            e)
                    )
                        break;
                    s.unshift(l.pop());
                }
                return (
                    (i.innerHTML = l.join("") + t.outerHTML + s.join("")),
                    c.parentNode.removeChild(c),
                    !0
                );
            }
            function an(t) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return un(t);
                    })(t) ||
                    (function (t) {
                        if (
                            ("undefined" != typeof Symbol &&
                                null != t[Symbol.iterator]) ||
                            null != t["@@iterator"]
                        )
                            return Array.from(t);
                    })(t) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return un(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return un(t, e);
                    })(t) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function un(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            function cn(t, e, n, r) {
                if (on(t, e, n, r, !0)) return !0;
                var o = document.querySelector(
                        'svg[data-pdf-annotate-page="'.concat(r, '"]'),
                    ),
                    i = o.getBoundingClientRect(),
                    a = an(
                        o.parentNode.querySelectorAll(
                            kt.textClassQuery() + " > div",
                        ),
                    );
                (n = X(o, { y: n }).y + i.top), (e = X(o, { x: e }).x + i.left);
                for (var u = 0, c = a.length; u < c; u++) {
                    var l = a[u];
                    if (n <= l.getBoundingClientRect().top)
                        return l.parentNode.insertBefore(t, l), !0;
                }
                var s = o.parentNode.querySelector(kt.textClassQuery());
                if (s && z(e, n, s.getBoundingClientRect()))
                    return s.appendChild(t), !0;
                return !1;
            }
            function ln(t) {
                if (t) {
                    var e = document.querySelector(
                        "#pdf-annotate-screenreader-".concat(
                            t.annotation,
                            " ol",
                        ),
                    );
                    if (e) {
                        var n = document.createElement("li");
                        n.setAttribute(
                            "id",
                            "pdf-annotate-screenreader-comment-".concat(t.uuid),
                        ),
                            n.appendChild(
                                document.createTextNode("".concat(t.content)),
                            ),
                            e.appendChild(n);
                    }
                }
            }
            function sn(t, e, n) {
                return (
                    Array.isArray(n)
                        ? Promise.resolve(n)
                        : In.getStoreAdapter().getComments(t, e)
                ).then(function (t) {
                    var n = document.getElementById(
                        "pdf-annotate-screenreader-".concat(e),
                    );
                    if (n) {
                        var r = document.createElement("ol");
                        r.setAttribute(
                            "id",
                            "pdf-annotate-screenreader-comment-list-".concat(e),
                        ),
                            r.setAttribute("aria-label", "Comments"),
                            n.appendChild(r),
                            t.forEach(ln);
                    }
                });
            }
            var dn = [
                "highlight",
                "point",
                "area",
                "circle",
                "emptycircle",
                "fillcircle",
            ];
            function fn(t, e, n) {
                In.getStoreAdapter()
                    .getAnnotations(t, e)
                    .then(function (t) {
                        return t.annotations.filter(function (t) {
                            return t.type === n;
                        });
                    })
                    .then(function (e) {
                        return (
                            e.forEach(function (e) {
                                pn(t, e.uuid);
                            }),
                            e
                        );
                    })
                    .then(hn);
            }
            function pn(t, e) {
                vn("pdf-annotate-screenreader-".concat(e)),
                    vn("pdf-annotate-screenreader-".concat(e, "-end"));
            }
            function yn(t, e, n) {
                var r = document.querySelector(
                    "pdf-annotate-screenreader-comment-list-".concat(e),
                );
                (r
                    ? Promise.resolve(!0)
                    : sn(t, e, []).then(function () {
                          return (
                              (r = document.querySelector(
                                  "pdf-annotate-screenreader-comment-list-".concat(
                                      e,
                                  ),
                              )),
                              !0
                          );
                      })
                ).then(function () {
                    ln(n);
                });
            }
            function mn(t, e) {
                vn("pdf-annotate-screenreader-comment-".concat(e));
            }
            function vn(t) {
                var e = document.getElementById(t);
                e && e.parentNode.removeChild(e);
            }
            function hn(t) {
                (t = Array.isArray(t) ? t : []),
                    Object.keys(wn).forEach(function (e) {
                        var n = wn[e];
                        t.filter(function (t) {
                            return t.type === e;
                        })
                            .sort(n)
                            .forEach(function (t, e) {
                                return (function (t) {
                                    var e =
                                        arguments.length > 1 &&
                                        void 0 !== arguments[1]
                                            ? arguments[1]
                                            : 0;
                                    switch (t.type) {
                                        case "highlight":
                                        case "strikeout":
                                            var n = t.rectangles,
                                                r = n[0],
                                                o = n[n.length - 1];
                                            on(
                                                en(
                                                    "Begin "
                                                        .concat(
                                                            t.type,
                                                            " annotation ",
                                                        )
                                                        .concat(e),
                                                    t.uuid,
                                                ),
                                                r.x,
                                                r.y,
                                                t.page,
                                                !0,
                                            ),
                                                on(
                                                    en(
                                                        "End "
                                                            .concat(
                                                                t.type,
                                                                " annotation ",
                                                            )
                                                            .concat(e),
                                                        "".concat(
                                                            t.uuid,
                                                            "-end",
                                                        ),
                                                    ),
                                                    o.x + o.width,
                                                    o.y,
                                                    t.page,
                                                    !1,
                                                );
                                            break;
                                        case "textbox":
                                        case "point":
                                            var i =
                                                "textbox" === t.type
                                                    ? " (content: ".concat(
                                                          t.content,
                                                          ")",
                                                      )
                                                    : "";
                                            cn(
                                                en(
                                                    ""
                                                        .concat(
                                                            t.type,
                                                            " annotation ",
                                                        )
                                                        .concat(e)
                                                        .concat(i),
                                                    t.uuid,
                                                ),
                                                t.x,
                                                t.y,
                                                t.page,
                                            );
                                            break;
                                        case "drawing":
                                        case "area":
                                            var a =
                                                    void 0 !== t.x
                                                        ? t.x
                                                        : t.lines[0][0],
                                                u =
                                                    void 0 !== t.y
                                                        ? t.y
                                                        : t.lines[0][1];
                                            cn(
                                                en("Unlabeled drawing", t.uuid),
                                                a,
                                                u,
                                                t.page,
                                            );
                                            break;
                                        case "circle":
                                        case "fillcircle":
                                        case "emptycircle":
                                            var c =
                                                    void 0 !== t.cx
                                                        ? t.cx
                                                        : t.lines[0][0],
                                                l =
                                                    void 0 !== t.cy
                                                        ? t.cy
                                                        : t.lines[0][1];
                                            cn(
                                                en("Unlabeled drawing", t.uuid),
                                                c,
                                                l,
                                                t.page,
                                            );
                                    }
                                    dn.includes(t.type) &&
                                        sn(t.documentId, t.uuid);
                                })(t, e + 1);
                            });
                    });
            }
            function gn(t, e) {
                return t.y < e.y ? t.x - e.x : 1;
            }
            function bn(t, e) {
                return gn(t.rectangles[0], e.rectangles[0]);
            }
            G("annotation:add", function (t, e, n) {
                fn(t, e, n.type);
            }),
                G("annotation:edit", function (t, e, n) {
                    fn(t, n.page, n.type);
                }),
                G("annotation:delete", pn),
                G("comment:add", yn),
                G("comment:delete", mn);
            var wn = {
                highlight: bn,
                strikeout: bn,
                drawing: function (t, e) {
                    var n = t.lines[0],
                        r = e.lines[0];
                    return gn({ x: n[0], y: n[1] }, { x: r[0], y: r[1] });
                },
                textbox: gn,
                point: gn,
                area: gn,
            };
            function xn(t, e) {
                return (
                    (function (t) {
                        if (Array.isArray(t)) return t;
                    })(t) ||
                    (function (t, e) {
                        var n =
                            null == t
                                ? null
                                : ("undefined" != typeof Symbol &&
                                      t[Symbol.iterator]) ||
                                  t["@@iterator"];
                        if (null == n) return;
                        var r,
                            o,
                            i = [],
                            a = !0,
                            u = !1;
                        try {
                            for (
                                n = n.call(t);
                                !(a = (r = n.next()).done) &&
                                (i.push(r.value), !e || i.length !== e);
                                a = !0
                            );
                        } catch (t) {
                            (u = !0), (o = t);
                        } finally {
                            try {
                                a || null == n.return || n.return();
                            } finally {
                                if (u) throw o;
                            }
                        }
                        return i;
                    })(t, e) ||
                    (function (t, e) {
                        if (!t) return;
                        if ("string" == typeof t) return An(t, e);
                        var n = Object.prototype.toString.call(t).slice(8, -1);
                        "Object" === n &&
                            t.constructor &&
                            (n = t.constructor.name);
                        if ("Map" === n || "Set" === n) return Array.from(t);
                        if (
                            "Arguments" === n ||
                            /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)
                        )
                            return An(t, e);
                    })(t, e) ||
                    (function () {
                        throw new TypeError(
                            "Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.",
                        );
                    })()
                );
            }
            function An(t, e) {
                (null == e || e > t.length) && (e = t.length);
                for (var n = 0, r = new Array(e); n < e; n++) r[n] = t[n];
                return r;
            }
            var Sn =
                '\n  <div style="visibility: hidden;" class="page" data-loaded="false">\n    <div class="canvasWrapper">\n      <canvas></canvas>\n    </div>\n    <div class="' +
                kt.textLayerName +
                '"></div>\n    <svg class="' +
                kt.annotationLayerName +
                '"></svg>\n  </div>\n';
            function En(t) {
                if (Math.floor(t) === t) return [t, 1];
                var e = 1 / t;
                if (e > 8) return [1, 8];
                if (Math.floor(e) === e) return [1, e];
                for (var n = t > 1 ? e : t, r = 0, o = 1, i = 1, a = 1; ; ) {
                    var u = r + i,
                        c = o + a;
                    if (c > 8) break;
                    n <= u / c ? ((i = u), (a = c)) : ((r = u), (o = c));
                }
                return n - r / o < i / a - n
                    ? n === t
                        ? [r, o]
                        : [o, r]
                    : n === t
                      ? [i, a]
                      : [a, i];
            }
            function Cn(t, e) {
                var n = t % e;
                return 0 === n ? t : Math.round(t - n + e);
            }
            var Ln = {
                    addEventListener: G,
                    removeEventListener: K,
                    fireEvent: V,
                    disableEdit: function () {
                        zt(), Bt && ((Bt = !1), K("annotation:click", Dt));
                    },
                    enableEdit: function () {
                        Bt || ((Bt = !0), G("annotation:click", Dt));
                    },
                    disablePen: function () {
                        Vt &&
                            ((Vt = !1),
                            document.removeEventListener("pointerdown", Zt),
                            document.removeEventListener("pointermove", ee),
                            document.removeEventListener("pointerup", te),
                            document.removeEventListener("keyup", ne),
                            D());
                    },
                    enablePen: function () {
                        Vt ||
                            ((Vt = !0),
                            document.addEventListener("pointerdown", Zt),
                            document.addEventListener("pointermove", ee),
                            document.addEventListener("pointerup", te),
                            document.addEventListener("keyup", ne),
                            H());
                    },
                    setPen: function () {
                        var t =
                                arguments.length > 0 && void 0 !== arguments[0]
                                    ? arguments[0]
                                    : 1,
                            e =
                                arguments.length > 1 && void 0 !== arguments[1]
                                    ? arguments[1]
                                    : "000000";
                        (Wt = Math.round(100 * parseFloat(t)) / 100), ($t = e);
                    },
                    disablePoint: function () {
                        Se &&
                            ((Se = !1),
                            document.removeEventListener("mouseup", Ee));
                    },
                    enablePoint: function () {
                        Se ||
                            ((Se = !0),
                            document.addEventListener("mouseup", Ee));
                    },
                    disableRect: function () {
                        Me &&
                            ((Me = !1),
                            document.removeEventListener("mouseup", qe),
                            document.removeEventListener("mousedown", Te),
                            document.removeEventListener("keyup", ze));
                    },
                    enableRect: function (t) {
                        (Ne = t),
                            Me ||
                                ((Me = !0),
                                document.addEventListener("mouseup", qe),
                                document.addEventListener("mousedown", Te),
                                document.addEventListener("keyup", ze));
                    },
                    disableCircle: function () {
                        Ye &&
                            ((Ye = !1),
                            document.removeEventListener("mouseup", He));
                    },
                    enableCircle: function (t) {
                        (Xe = t),
                            Ye ||
                                ((Ye = !0),
                                document.addEventListener("mouseup", He));
                    },
                    setCircle: function () {
                        var t =
                                arguments.length > 0 && void 0 !== arguments[0]
                                    ? arguments[0]
                                    : 10,
                            e =
                                arguments.length > 1 && void 0 !== arguments[1]
                                    ? arguments[1]
                                    : "0000FF";
                        (Ue = parseInt(t, 10)), (Qe = e);
                    },
                    addCircle: function (t, e) {
                        var n = Xe;
                        (Xe = t), He(e), (Xe = n);
                    },
                    disableArrow: function () {
                        le &&
                            ((le = !1),
                            document.removeEventListener("mousedown", se),
                            document.removeEventListener("keyup", pe),
                            D());
                    },
                    enableArrow: function () {
                        le ||
                            ((le = !0),
                            document.addEventListener("mousedown", se),
                            document.addEventListener("keyup", pe),
                            H());
                    },
                    setArrow: function () {
                        var t =
                                arguments.length > 0 && void 0 !== arguments[0]
                                    ? arguments[0]
                                    : 10,
                            e =
                                arguments.length > 1 && void 0 !== arguments[1]
                                    ? arguments[1]
                                    : "0000FF";
                        (re = parseInt(t, 10)), (oe = e);
                    },
                    disableEraser: function () {
                        document.removeEventListener("pointermove", we),
                            document.removeEventListener("pointerdown", ge),
                            document.removeEventListener("pointerup", be);
                    },
                    enableEraser: function () {
                        document.addEventListener("pointermove", we),
                            document.addEventListener("pointerdown", ge),
                            document.addEventListener("pointerup", be);
                    },
                    disableText: function () {
                        We &&
                            ((We = !1),
                            document.removeEventListener("mouseup", Ve));
                    },
                    enableText: function () {
                        We ||
                            ((We = !0),
                            document.addEventListener("mouseup", Ve));
                    },
                    setText: function () {
                        var t =
                                arguments.length > 0 && void 0 !== arguments[0]
                                    ? arguments[0]
                                    : 12,
                            e =
                                arguments.length > 1 && void 0 !== arguments[1]
                                    ? arguments[1]
                                    : "000000";
                        ($e = parseInt(t, 10)), (Je = e);
                    },
                    createPage: function (t) {
                        var e = document.createElement("div");
                        e.innerHTML = Sn;
                        var n = e.children[0],
                            r = n.querySelector("canvas");
                        return (
                            n.setAttribute("id", "pageContainer".concat(t)),
                            n.setAttribute("data-page-number", t),
                            (r.mozOpaque = !0),
                            r.setAttribute("id", "page".concat(t)),
                            n
                        );
                    },
                    renderPage: function (t, e) {
                        var n = e.documentId,
                            r = e.pdfDocument,
                            o = e.scale,
                            i = e.rotate,
                            a = new pdfjsViewer.EventBus();
                        return Promise.all([
                            r.getPage(t),
                            In.getAnnotations(n, t),
                        ]).then(function (e) {
                            var n = xn(e, 2),
                                r = n[0],
                                u = n[1],
                                c = document.getElementById(
                                    "pageContainer".concat(t),
                                ),
                                l = c.querySelector(kt.annotationClassQuery()),
                                s = c
                                    .querySelector(".canvasWrapper canvas")
                                    .getContext("2d", { alpha: !1 }),
                                d = (i + r.rotate) % 360,
                                f = r.getViewport({ scale: o, rotation: d }),
                                p = (function (t, e, n) {
                                    var r = document.getElementById(
                                            "pageContainer".concat(t),
                                        ),
                                        o = r.querySelector(
                                            ".canvasWrapper canvas",
                                        ),
                                        i = r.querySelector(
                                            kt.annotationClassQuery(),
                                        ),
                                        a = r.querySelector(".canvasWrapper"),
                                        u = r.querySelector(
                                            kt.textClassQuery(),
                                        ),
                                        c =
                                            ((f = n),
                                            (p = window.devicePixelRatio || 1),
                                            (y =
                                                f.webkitBackingStorePixelRatio ||
                                                f.mozBackingStorePixelRatio ||
                                                f.msBackingStorePixelRatio ||
                                                f.oBackingStorePixelRatio ||
                                                f.backingStorePixelRatio ||
                                                1),
                                            (m = p / y),
                                            { sx: m, sy: m, scaled: 1 !== m }),
                                        l = c.scaled
                                            ? [c.sx, 0, 0, c.sy, 0, 0]
                                            : null,
                                        s = En(c.sx),
                                        d = En(c.sy);
                                    var f, p, y, m;
                                    return (
                                        (r.style.visibility = ""),
                                        (o.width = Cn(e.width * c.sx, s[0])),
                                        (o.height = Cn(e.height * c.sy, d[0])),
                                        (o.style.width =
                                            Cn(e.width, s[1]) + "px"),
                                        (o.style.height =
                                            Cn(e.height, s[1]) + "px"),
                                        i.setAttribute("width", e.width),
                                        i.setAttribute("height", e.height),
                                        (i.style.width = "".concat(
                                            e.width,
                                            "px",
                                        )),
                                        (i.style.height = "".concat(
                                            e.height,
                                            "px",
                                        )),
                                        (r.style.width = "".concat(
                                            e.width,
                                            "px",
                                        )),
                                        (r.style.height = "".concat(
                                            e.height,
                                            "px",
                                        )),
                                        (a.style.width = "".concat(
                                            e.width,
                                            "px",
                                        )),
                                        (a.style.height = "".concat(
                                            e.height,
                                            "px",
                                        )),
                                        (u.style.width = "".concat(
                                            e.width,
                                            "px",
                                        )),
                                        (u.style.height = "".concat(
                                            e.height,
                                            "px",
                                        )),
                                        l
                                    );
                                })(t, f, s);
                            return Promise.all([
                                r.render({
                                    canvasContext: s,
                                    viewport: f,
                                    transform: p,
                                }).promise,
                                In.render(l, f, u),
                            ])
                                .then(function () {
                                    return r
                                        .getTextContent({
                                            normalizeWhitespace: !0,
                                        })
                                        .then(function (e) {
                                            return new Promise(function (n, r) {
                                                var o = c.querySelector(
                                                        kt.textClassQuery(),
                                                    ),
                                                    i =
                                                        new pdfjsViewer.TextLayerBuilder(
                                                            o,
                                                            t - 1,
                                                            f,
                                                            !1,
                                                            a,
                                                        );
                                                i.setTextContentSource(e);
                                                    i.render(f);
                                                    setTimeout(function () {
                                                        try {
                                                            hn(u.annotations),
                                                                n();
                                                        } catch (t) {
                                                            r(t);
                                                        }
                                                    });
                                            });
                                        });
                                })
                                .then(function () {
                                    return (
                                        c.setAttribute("data-loaded", "true"),
                                        [r, u]
                                    );
                                });
                        });
                    },
                },
                In = {
                    findAnnotationAtPoint: q,
                    findSVGContainer: T,
                    convertToScreenPoint: U,
                    StoreAdapter: et,
                    LocalStoreAdapter: dt,
                    LocalUserStoreAdapter: Et,
                    __storeAdapter: new et(),
                    getStoreAdapter: function () {
                        return this.__storeAdapter;
                    },
                    setStoreAdapter: function (t) {
                        this.__storeAdapter = t;
                    },
                    UI: Ln,
                    render: function (t, e, n) {
                        return new Promise(function (r, o) {
                            return (
                                t.setAttribute(
                                    "data-pdf-annotate-container",
                                    !0,
                                ),
                                t.setAttribute(
                                    "data-pdf-annotate-viewport",
                                    JSON.stringify(e),
                                ),
                                t.removeAttribute("data-pdf-annotate-document"),
                                t.removeAttribute("data-pdf-annotate-page"),
                                n
                                    ? (t.setAttribute(
                                          "data-pdf-annotate-document",
                                          n.documentId,
                                      ),
                                      t.setAttribute(
                                          "data-pdf-annotate-page",
                                          n.pageNumber,
                                      ),
                                      Array.isArray(n.annotations) &&
                                      0 !== n.annotations.length
                                          ? (n.annotations.forEach(
                                                function (n) {
                                                    var r = t.querySelector(
                                                        '[data-pdf-annotate-id="' +
                                                            n.uuid +
                                                            '"]',
                                                    );
                                                    r ? P(t, r, e) : j(t, n, e);
                                                },
                                            ),
                                            void r(t))
                                          : r(t))
                                    : ((t.innerHTML = ""), r(t))
                            );
                        });
                    },
                    getAnnotations: function (t, e) {
                        var n;
                        return (n =
                            this.getStoreAdapter()).getAnnotations.apply(
                            n,
                            arguments,
                        );
                    },
                    config: kt,
                    uuid: ot,
                };
            e.default = In;
        },
    ]);
});
//# sourceMappingURL=pdf-annotate.min.js.map
