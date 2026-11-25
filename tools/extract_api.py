#!/usr/bin/env python3
"""Extract full API metadata from bin/ZhaniyaApi.dll -> api_catalog.json"""
import json
import dnfile
from collections import defaultdict

DLL = "bin/ZhaniyaApi.dll"
OUT = "tools/api_catalog.json"

# ---- element type names ----
ET = {
    0x01: "void", 0x02: "bool", 0x03: "char", 0x04: "sbyte", 0x05: "byte",
    0x06: "short", 0x07: "ushort", 0x08: "int", 0x09: "uint", 0x0A: "long",
    0x0B: "ulong", 0x0C: "float", 0x0D: "double", 0x0E: "string",
    0x18: "IntPtr", 0x19: "UIntPtr", 0x1C: "object",
}


class SigReader:
    def __init__(self, data, md):
        self.d = data
        self.p = 0
        self.md = md

    def u8(self):
        b = self.d[self.p]
        self.p += 1
        return b

    def compressed(self):
        b = self.u8()
        if (b & 0x80) == 0:
            return b
        if (b & 0xC0) == 0x80:
            return ((b & 0x3F) << 8) | self.u8()
        return ((b & 0x1F) << 24) | (self.u8() << 16) | (self.u8() << 8) | self.u8()

    def typedefordef(self, code):
        tag = code & 3
        idx = code >> 2
        try:
            if tag == 0:
                row = self.md.TypeDef.rows[idx - 1]
            elif tag == 1:
                row = self.md.TypeRef.rows[idx - 1]
            else:
                return f"TypeSpec#{idx}"
            ns = str(getattr(row, "TypeNamespace", "") or "")
            n = str(getattr(row, "TypeName", "") or "")
            return f"{ns}.{n}" if ns else n
        except Exception:
            return f"TDR#{idx}"

    def type(self, allow_byref=True):
        et = self.u8()
        if et in ET:
            return ET[et]
        if et == 0x10:  # BYREF
            return "ref " + self.type()
        if et in (0x11, 0x12):  # VALUETYPE / CLASS
            code = self.compressed()
            return self.typedefordef(code)
        if et == 0x1D:  # SZARRAY
            return self.type() + "[]"
        if et == 0x15:  # GENERICINST
            kind = self.u8()  # CLASS/VALUETYPE
            code = self.compressed()
            base = self.typedefordef(code)
            n = self.compressed()
            args = [self.type() for _ in range(n)]
            return f"{base}<{', '.join(args)}>"
        if et in (0x13, 0x1E):  # VAR / MVAR
            return f"!{self.compressed()}"
        if et == 0x1F or et == 0x20:  # CMOD
            self.compressed()
            return self.type()
        if et == 0x41:  # SENTINEL
            return "..."
        return f"et_0x{et:02x}"

    def method_sig(self):
        cc = self.u8()
        if cc & 0x10:  # generic
            self.compressed()
        nparams = self.compressed()
        ret = self.type()
        params = [self.type() for _ in range(nparams)]
        return ret, params


def heap_str(h, i):
    try:
        v = h.get(i)
        return str(v) if v is not None else None
    except Exception:
        return None


def decode_attr_value(b):
    if b is None:
        return []
    if hasattr(b, "value"):
        data = bytes(b.value)
    else:
        data = bytes(b)
    if len(data) < 2:
        return []
    pos = 2
    out = []

    def read_ser(pos):
        if pos >= len(data):
            return None, pos
        n = data[pos]
        if n == 0xFF:
            return None, pos + 1
        if (n & 0x80) == 0:
            ln, pos = n, pos + 1
        elif (n & 0xC0) == 0x80:
            ln = ((n & 0x3F) << 8) | data[pos + 1]
            pos += 2
        else:
            ln = ((n & 0x1F) << 24) | (data[pos + 1] << 16) | (data[pos + 2] << 8) | data[pos + 3]
            pos += 4
        if pos + ln > len(data):
            return None, pos
        return data[pos:pos + ln].decode("utf-8", "replace"), pos + ln

    while pos < len(data):
        if data[pos] in (0, 1) and pos + 1 == len(data):
            out.append(bool(data[pos]))
            break
        s, pos2 = read_ser(pos)
        if s is None:
            break
        out.append(s)
        pos = pos2
    return out


def main():
    pe = dnfile.dnPE(DLL)
    md = pe.net.mdtables

    # ---- attribute ctor name ----
    def attr_ctor_name(ca):
        try:
            m = ca.Type.row
            clsrow = m.Class.row
            ns = str(getattr(clsrow, "TypeNamespace", "") or "")
            n = str(getattr(clsrow, "TypeName", "") or "")
            return (f"{ns}.{n}" if ns else n).split(".")[-1]
        except Exception:
            return "?"

    by_parent = defaultdict(list)
    for ca in md.CustomAttribute.rows:
        by_parent[(str(ca.Parent.table.name), ca.Parent.row_index)].append(
            (attr_ctor_name(ca), decode_attr_value(ca.Value))
        )

    # ---- user strings heap (route templates etc.) ----
    user_strings = []
    try:
        us = pe.net.user_strings
        size = us.sizeof()
        off = 1
        # walk heap: [compressed len][bytes + terminal 0]
        raw = us  # use get_bytes
        # fallback: try known offsets via get until failure
        import struct as _s
        blob = None
        try:
            # read raw stream from file
            fo = us.file_offset
            with open(DLL, "rb") as f:
                f.seek(fo)
                blob = f.read(size)
        except Exception:
            blob = None
        if blob:
            pos = 1
            while pos < len(blob):
                b0 = blob[pos]
                if (b0 & 0x80) == 0:
                    ln, hdr = b0, 1
                elif (b0 & 0xC0) == 0x80:
                    if pos + 1 >= len(blob):
                        break
                    ln = ((b0 & 0x3F) << 8) | blob[pos + 1]
                    hdr = 2
                else:
                    if pos + 3 >= len(blob):
                        break
                    ln = ((b0 & 0x1F) << 24) | (blob[pos + 1] << 16) | (blob[pos + 2] << 8) | blob[pos + 3]
                    hdr = 4
                start = pos + hdr
                end = start + ln
                if end > len(blob):
                    break
                payload = blob[start:end - 1]  # strip terminal byte
                try:
                    s = payload.decode("utf-16-le", "replace")
                except Exception:
                    s = payload.decode("utf-8", "replace")
                if s:
                    user_strings.append(s)
                pos = end
    except Exception as e:
        print("user strings err:", e)

    # ---- controllers ----
    tds = md.TypeDef.rows
    catalog = {
        "assembly": DLL,
        "user_strings": sorted(set(user_strings)),
        "controllers": [],
    }

    for ti, td in enumerate(tds):
        tname = str(td.TypeName)
        if not tname.endswith("Controller"):
            continue
        tns = str(td.TypeNamespace or "")
        tattrs = by_parent.get(("TypeDef", ti + 1), [])
        route_prefix = None
        authorize = False
        for an, aa in tattrs:
            if an == "RoutePrefixAttribute" and aa:
                route_prefix = aa[0]
            if an == "AuthorizeAttribute":
                authorize = True
            if an == "ApiControllerAttribute":
                pass

        actions = []
        for mi in td.MethodList:
            m = mi.row
            mname = str(m.Name)
            if mname.startswith(("<", ".", "get_", "set_")):
                continue
            mattrs = by_parent.get(("MethodDef", mi.row_index), [])
            # HTTP method + template
            http_methods = []
            template = None
            has_auth = authorize
            allow_anon = False
            for an, aa in mattrs:
                if an == "HttpGetAttribute":
                    http_methods.append("GET")
                    if aa:
                        template = aa[0]
                elif an == "HttpPostAttribute":
                    http_methods.append("POST")
                    if aa:
                        template = aa[0]
                elif an == "HttpPutAttribute":
                    http_methods.append("PUT")
                    if aa:
                        template = aa[0]
                elif an == "HttpDeleteAttribute":
                    http_methods.append("DELETE")
                    if aa:
                        template = aa[0]
                elif an == "HttpPatchAttribute":
                    http_methods.append("PATCH")
                    if aa:
                        template = aa[0]
                elif an == "RouteAttribute" and aa:
                    template = aa[0]
                elif an == "AuthorizeAttribute":
                    has_auth = True
                elif an == "AllowAnonymousAttribute":
                    allow_anon = True
                    has_auth = False

            # signature -> param types
            params = []
            ret = None
            try:
                sig = m.Signature
                data = bytes(sig.value)
                r = SigReader(data, md)
                ret, ptypes = r.method_sig()
                # param names
                pnames = []
                for pi in mi.row.ParamList:
                    pn = str(pi.row.Name) if hasattr(pi, "row") else str(pi.Name)
                    if pn and pn != "value":
                        pnames.append(pn)
                for i2, pt in enumerate(ptypes):
                    nm = pnames[i2] if i2 < len(pnames) else ""
                    if not nm or nm.startswith("arg") or nm == "value":
                        # نام از روی نوع: InvoiceModel → invoice
                        base = pt.split(".")[-1].split("`")[0]
                        if base and base not in ("String", "Object", "Nullable"):
                            nm = base[0].lower() + base[1:] if len(base) > 1 else base
                        else:
                            nm = f"arg{i2}"
                    params.append({"name": nm, "type": pt})
            except Exception as e:
                params = params or []

            try:
                is_public = (int(m.Flags) & 0x6) == 0x6
            except Exception:
                is_public = True
            if not is_public:
                continue

            actions.append({
                "name": mname,
                "http": http_methods or [],
                "template": template,
                "params": params,
                "returns": ret,
                "authorize": has_auth,
                "allowAnonymous": allow_anon,
                "attrs": [a[0] for a in mattrs],
            })

        catalog["controllers"].append({
            "name": tname,
            "namespace": tns,
            "routePrefix": route_prefix,
            "authorize": authorize,
            "classAttrs": [a[0] for a in tattrs],
            "actions": actions,
        })

    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(catalog, f, indent=2, ensure_ascii=False)
    print(f"wrote {OUT}: {len(catalog['controllers'])} controllers, "
          f"{sum(len(c['actions']) for c in catalog['controllers'])} actions, "
          f"{len(catalog['user_strings'])} user strings")


if __name__ == "__main__":
    main()
