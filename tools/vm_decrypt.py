#!/usr/bin/env python3
"""Interpret the obfuscated string-table initializer (a253xoPyrm) of ZhaniyaApi.dll
and dump the decrypted table, then extract all protected strings."""
import struct
import sys
import dnfile
from dncil.cil.body import CilMethodBody
from dncil.cil.body.reader import CilMethodBodyReaderBytes
from Crypto.Cipher import AES

DLL = "bin/ZhaniyaApi.dll"

pe = dnfile.dnPE(DLL)
md = pe.net.mdtables
US = pe.net.user_strings


def memberref_name(idx):
    r = md.MemberRef.rows[idx - 1]
    try:
        cls = r.Class.row
        ns = str(getattr(cls, "TypeNamespace", "") or "")
        cn = str(getattr(cls, "TypeName", None) or getattr(cls, "Name", "?"))
        cn = f"{ns}.{cn}" if ns else cn
    except Exception:
        cn = "?"
    return cn, str(r.Name)


def typeref_name(idx):
    r = md.TypeRef.rows[idx - 1]
    ns = str(getattr(r, "TypeNamespace", "") or "")
    n = str(r.TypeName)
    return f"{ns}.{n}" if ns else n


# ---------------- object model ----------------
class StreamObj:
    def __init__(self, data=b""):
        self.buf = bytearray(data)
        self.pos = 0

    @property
    def length(self):
        return len(self.buf)

    @length.setter
    def length(self, v):
        pass

    def set_Position(self, v):
        self.pos = int(v)

    def get_Length(self):
        return len(self.buf)

    def Read(self, buf, off, count):
        n = min(count, len(self.buf) - self.pos)
        if n <= 0:
            return 0
        buf[off:off + n] = self.buf[self.pos:self.pos + n]
        self.pos += n
        return n

    def Write(self, data, off, count):
        d = bytes(data[off:off + count])
        if self.pos == len(self.buf):
            self.buf.extend(d)
        else:
            self.buf[self.pos:self.pos + len(d)] = d
        self.pos += len(d)

    def Close(self):
        pass

    def ToArray(self):
        return bytes(self.buf)


class MemStream(StreamObj):
    pass


class BinaryReader:
    def __init__(self, stream):
        self.stream = stream

    def get_BaseStream(self):
        return self.stream

    def ReadBytes(self, n):
        s = self.stream
        end = min(s.pos + n, len(s.buf))
        out = bytes(s.buf[s.pos:end])
        s.pos = end
        return out

    def ReadInt32(self):
        b = self.ReadBytes(4)
        return struct.unpack("<i", b)[0] if len(b) == 4 else 0

    def ReadUInt32(self):
        b = self.ReadBytes(4)
        return struct.unpack("<I", b)[0] if len(b) == 4 else 0

    def Read(self, buf, off, count):
        return self.stream.Read(buf, off, count)

    def Close(self):
        pass


class AesObj:
    def __init__(self):
        self.key = None
        self.iv = None
        self.mode = 1  # CBC

    def set_Key(self, k):
        self.key = bytes(k)

    def set_IV(self, v):
        self.iv = bytes(v)

    def set_Mode(self, m):
        self.mode = int(m)

    def CreateDecryptor(self, *args):
        if len(args) >= 2:
            key, iv = bytes(args[0]), bytes(args[1])
        else:
            key, iv = self.key, self.iv
        return Transform(key, iv)

    def CreateEncryptor(self, *args):
        if len(args) >= 2:
            key, iv = bytes(args[0]), bytes(args[1])
        else:
            key, iv = self.key, self.iv
        return Transform(key, iv)


class Transform:
    def __init__(self, key, iv):
        self.key = key
        self.iv = iv

    def decrypt(self, data):
        if not data:
            return b""
        cipher = AES.new(self.key, AES.MODE_CBC, self.iv)
        out = cipher.decrypt(bytes(data))
        # PKCS7 unpad
        if out:
            pad = out[-1]
            if 1 <= pad <= 16 and out[-pad:] == bytes([pad]) * pad:
                out = out[:-pad]
        return out

    def encrypt(self, data):
        pad = 16 - (len(data) % 16)
        data = bytes(data) + bytes([pad]) * pad
        cipher = AES.new(self.key, AES.MODE_CBC, self.iv)
        return cipher.encrypt(data)


class CryptoStreamObj:
    """Write-mode crypto stream: buffers ciphertext, decrypts on FlushFinalBlock."""
    def __init__(self, underlying, transform, mode=1):
        self.underlying = underlying
        self.transform = transform
        self.mode = mode
        self.pending = bytearray()

    def Write(self, data, off, count):
        self.pending.extend(bytes(data[off:off + count]))

    def FlushFinalBlock(self):
        out = self.transform.decrypt(self.pending)
        if self.underlying is not None:
            self.underlying.Write(out, 0, len(out))
        self.pending = bytearray()

    def Close(self):
        if self.pending:
            self.FlushFinalBlock()


def unwrap_reader(obj):
    if isinstance(obj, BinaryReader):
        return obj
    if obj is not None:
        for v in vars(obj).values():
            if isinstance(v, BinaryReader):
                return v
    return obj


class BCL:
    """Semantic implementations of external (MemberRef) calls."""
    @staticmethod
    def call(type_name, method, recv, args):
        key = f"{type_name}::{method}"
        if "BinaryReader::" in key and recv is not None and not isinstance(recv, BinaryReader):
            recv = unwrap_reader(recv)
        # ---- BinaryReader ----
        if key.endswith("BinaryReader::.ctor"):
            return BinaryReader(args[0])
        if key.endswith("BinaryReader::get_BaseStream"):
            return recv.get_BaseStream()
        if key.endswith("BinaryReader::ReadBytes"):
            return recv.ReadBytes(args[0])
        if key.endswith("BinaryReader::ReadInt32"):
            return recv.ReadInt32()
        if key.endswith("BinaryReader::ReadUInt32"):
            return recv.ReadUInt32()
        if key.endswith("BinaryReader::Read"):
            return recv.Read(args[0], args[1], args[2])
        if key.endswith("BinaryReader::Close"):
            recv.Close()
            return None
        # ---- Streams ----
        if key.endswith("Stream::get_Length"):
            return recv.get_Length()
        if key.endswith("Stream::set_Position"):
            recv.set_Position(args[0])
            return None
        if key.endswith("Stream::Read"):
            return recv.Read(args[0], args[1], args[2])
        if key.endswith("Stream::Write"):
            recv.Write(args[0], args[1], args[2])
            return None
        if key.endswith("Stream::Close"):
            recv.Close()
            return None
        if key.endswith("MemoryStream::.ctor"):
            if args:
                return MemStream(bytes(args[0]))
            return MemStream()
        if key.endswith("MemoryStream::ToArray"):
            return recv.ToArray()
        # ---- Crypto ----
        if key.endswith("AesCryptoServiceProvider::.ctor") or key.endswith("RijndaelManaged::.ctor"):
            return AesObj()
        if key.endswith("SymmetricAlgorithm::set_Key"):
            recv.set_Key(args[0]); return None
        if key.endswith("SymmetricAlgorithm::set_IV"):
            recv.set_IV(args[0]); return None
        if key.endswith("SymmetricAlgorithm::set_Mode"):
            recv.set_Mode(args[0]); return None
        if key.endswith("SymmetricAlgorithm::CreateDecryptor"):
            import sys as _s
            k = args[0] if len(args)>=2 else getattr(recv,'key',None)
            iv = args[1] if len(args)>=2 else getattr(recv,'iv',None)
            print(f"[AES] key={bytes(k).hex() if k else None} iv={bytes(iv).hex() if iv else None}", file=_s.stderr)
            return recv.CreateDecryptor(*args)
        if key.endswith("SymmetricAlgorithm::CreateEncryptor"):
            return recv.CreateEncryptor(*args)
        if key.endswith("CryptoStream::.ctor"):
            return CryptoStreamObj(args[0], args[1], args[2] if len(args) > 2 else 1)
        if key.endswith("CryptoStream::FlushFinalBlock"):
            recv.FlushFinalBlock(); return None
        if key.endswith("CryptoStream::Write"):
            recv.Write(args[0], args[1], args[2]); return None
        if key.endswith("HashAlgorithm::TransformBlock"):
            # (inputBuffer, inputOffset, inputCount, outputBuffer, outputOffset)
            return args[3]
        if key.endswith("HashAlgorithm::ComputeHash"):
            import hashlib
            return hashlib.md5(bytes(args[0])).digest()
        if key.endswith("MD5CryptoServiceProvider::.ctor"):
            return object()
        if key.endswith("CryptoConfig::get_AllowOnlyFipsAlgorithms"):
            return False
        # ---- Array / Convert / Encoding ----
        if key.endswith("Array::Reverse"):
            recv.reverse() if isinstance(recv, list) else None
            if isinstance(recv, (bytearray, list)):
                # Array.Reverse(arr) mutates in place
                r = recv
                for i in range(len(r) // 2):
                    r[i], r[len(r) - 1 - i] = r[len(r) - 1 - i], r[i]
            return None
        if key.endswith("Convert::ToBase64String"):
            import base64
            return base64.b64encode(bytes(args[0] if args else b"")).decode()
        if key.endswith("Encoding::GetBytes"):
            enc = recv
            if isinstance(enc, str):
                enc = "utf-8"
            return str(args[0]).encode(enc if isinstance(enc, str) else "utf-8")
        if key.endswith("Encoding::GetString"):
            return bytes(args[0]).decode("utf-16-le" if recv in (None, "utf-16-le") else "utf-8", "replace")
        if key.endswith("Encoding::get_Unicode"):
            return "utf-16-le"
        if key.endswith("String::StartsWith"):
            return str(recv).startswith(str(args[0]))
        if key.endswith("String::get_Length"):
            return len(str(recv))
        if key.endswith("String::get_Chars"):
            return str(recv)[args[0]]
        # ---- Reflection / anti-tamper (benign) ----
        if key.endswith("Assembly::GetName"):
            return AssemblyName()
        if key.endswith("AssemblyName::GetPublicKeyToken"):
            return None  # unsigned assembly
        if key.endswith("Assembly::get_EntryPoint"):
            import sys as _s
            print(f"[get_EntryPoint recv={type(recv).__name__} -> None]", file=_s.stderr)
            return None  # web app: no Main
        if key.endswith("MethodInfo::op_Equality"):
            r = args[0] == args[1]
            import sys as _s
            print(f"[op_Equality {args[0]!r} == {args[1]!r} -> {r}]", file=_s.stderr)
            return r
        if key.endswith("Assembly::op_Equality"):
            return args[0] == args[1]
        if key.endswith("Assembly::op_Inequality"):
            return args[0] != args[1]
        if key.endswith("StackFrame::.ctor"):
            return StackFrameObj()
        if key.endswith("StackFrame::GetMethod"):
            return MethodInfo()
        if key.endswith("MemberInfo::get_DeclaringType"):
            return TypeObj()
        if key.endswith("MemberInfo::get_MetadataToken"):
            return 0
        if key.endswith("Type::GetTypeFromHandle"):
            return TypeObj()
        if key.endswith("Type::get_Assembly"):
            return AssemblyObj()
        if key.endswith("Assembly::get_ManifestModule"):
            return ModuleObj()
        if key.endswith("Assembly::GetManifestResourceStream"):
            return StreamObj(get_resource_bytes(str(args[0])))
        if key.endswith("Type::get_Module"):
            return ModuleObj()
        if key.endswith("Module::ResolveMethod"):
            return MethodInfo()
        if key.endswith("MethodBase::get_IsStatic"):
            return True
        if key.endswith("MethodInfo::get_ReturnType"):
            return TypeObj()
        if key.endswith("Type::MakeByRefType"):
            return TypeObj()
        if key.endswith("Type::GetFields"):
            return []
        if key.endswith("Type::GetGenericArguments"):
            return []
        if key.endswith("FieldInfo::get_FieldType"):
            return TypeObj()
        if key.endswith("FieldInfo::SetValue"):
            return None
        if key.endswith("Type::get_IsValueType"):
            return False
        if key.endswith("ParameterInfo::get_ParameterType"):
            return TypeObj()
        if key.endswith("MethodBase::GetParameters"):
            return []
        if key.endswith("Delegate::CreateDelegate"):
            return None
        if key.endswith("RuntimeHelpers::InitializeArray"):
            # copy FieldRVA data into array
            arr, token = args[0], args[1]
            data = get_field_rva_data(token)
            if data is not None and arr is not None:
                n = min(len(arr), len(data))
                arr[:n] = data[:n]
            return None
        if key.endswith("RuntimeHelpers::get_OffsetToStringData"):
            return 0
        if key.endswith("Object::.ctor"):
            return None
        if key.endswith("Monitor::Enter") or key.endswith("Monitor::Exit"):
            return None
        if key.endswith("GC::SuppressFinalize") or key.endswith("Object::GetType"):
            return TypeObj()
        if key.endswith("Activator::CreateInstance"):
            t = args[0]
            if isinstance(t, str) and "Aes" in t:
                return AesObj()
            return object()
        if key.endswith("ObjectHandle::Unwrap"):
            return args[0]
        if key.endswith("Exception::.ctor"):
            return ExceptionObj(str(args[0]) if args else "")
        # default
        raise NotImplementedError(f"BCL call not mapped: {key} (recv={type(recv).__name__})")


class Dyn:
    pass


class AssemblyName: pass
class StackFrameObj: pass
class MethodInfo: pass
class TypeObj: pass
class AssemblyObj: pass
class ModuleObj: pass
class ExceptionObj(Exception): pass


def get_resource_bytes(name):
    for r in pe.net.resources:
        if str(r.name) == name:
            return bytes(r.data)
    return b""


def get_field_rva_data(token):
    table = token >> 24
    idx = token & 0xFFFFFF
    if table != 0x04:
        return None
    for fr in md.FieldRva.rows:
        fidx = fr.Field.row_index if hasattr(fr.Field, "row_index") else None
        if fidx == idx:
            # size from field signature (array?) — take up to 64 bytes
            return pe.get_data(fr.Rva, 64)
    return None


# ---------------- interpreter ----------------
class Frame:
    def __init__(self, body, args, module):
        self.body = body
        self.args = args          # includes 'this' at [0] for instance methods
        self.locals = [None] * 64
        self.stack = []
        self.module = module
        self.by_off = {ins.offset: ins for ins in body.instructions}
        self.sorted_offs = [ins.offset for ins in body.instructions]
        self.next_map = {}
        for i in range(len(self.sorted_offs) - 1):
            self.next_map[self.sorted_offs[i]] = self.sorted_offs[i + 1]
        self.pc = self.sorted_offs[0] if self.sorted_offs else 0
        self.finished = False
        self.result = None


I32 = 0xFFFFFFFF


def to_i32(v):
    v &= I32
    return v - 0x100000000 if v >= 0x80000000 else v


def to_u32(v):
    if isinstance(v, float):
        v = int(v)
    return v & I32


def is_wide(*vals):
    """True if any operand needs 64-bit context (from conv.u8 etc.)."""
    return any(v > 0xFFFFFFFF or v < -0x80000000 for v in vals if isinstance(v, int))


def fit(v, *ctx):
    if is_wide(*ctx):
        return v & 0xFFFFFFFFFFFFFFFF
    return to_i32(v)


class Interp:
    def __init__(self):
        self.fields = {0x473: bytearray(0)}  # table (empty initially, per .cctor)
        self.call_stack = []
        self.max_steps = 5_000_000

    def get_body(self, row):
        if not row.Rva:
            raise RuntimeError(f"no RVA for method {row.Name}")
        data = pe.get_data(row.Rva, 70000)
        return CilMethodBody(CilMethodBodyReaderBytes(data))

    def run_method(self, row, args):
        body = self.get_body(row)
        return self.run_body(body, args, vm=(str(row.Name) == "a253xoPyrm"))

    def run_body(self, body, args, vm=False):
        f = Frame(body, args, self)
        f.is_vm = vm
        steps = 0
        while not f.finished:
            steps += 1
            if steps > self.max_steps:
                raise RuntimeError("step limit exceeded")
            self.step(f)
        return f.result

    def step(self, f):
        ins = f.by_off.get(f.pc)
        if ins is None:
            # skip to next known offset
            nxt = [o for o in f.sorted_offs if o > f.pc]
            if not nxt:
                f.finished = True
                return
            f.pc = nxt[0]
            return
        op = ins.opcode.name
        operand = ins.operand
        if getattr(f, "is_vm", False) and not hasattr(self, "_trace"):
            self._trace = []
        if getattr(f, "is_vm", False):
            self._trace.append((f.pc, op, operand if not hasattr(operand, "value") else hex(getattr(operand, "value", 0))))
            if len(self._trace) > 40:
                self._trace.pop(0)
        if getattr(f, "is_vm", False) and f.pc == 0x1D7F and not getattr(self, "_hit1d7f", False):
            self._hit1d7f = True
            import sys as _s
            print("=== reached 0x1d7f, trace:", file=_s.stderr)
            for t in self._trace:
                print(f"   {t[0]:#06x} {t[1]} {t[2]}", file=_s.stderr)
        nxt = self.next_off(f, ins)
        st = f.stack

        def push(v):
            st.append(v)

        def pop():
            if not st:
                raise RuntimeError(f"stack underflow at {f.pc:#x} {op}")
            return st.pop()

        # ---- constants / loads ----
        if op == "nop" or op.startswith("prefix"):
            pass
        elif op == "ldc.i4" or op == "ldc.i4.s":
            push(to_i32(int(operand)))
        elif op == "ldc.i4.m1":
            push(-1)
        elif op.startswith("ldc.i4."):
            push(int(op.split(".")[-1]))
        elif op == "ldc.i8":
            push(int(operand))
        elif op == "ldnull":
            push(None)
        elif op == "ldarg" or op == "ldarg.s":
            idx = int(operand.index) if hasattr(operand, "index") else int(operand)
            push(f.args[idx] if idx < len(f.args) else None)
        elif op == "ldarg.0":
            push(f.args[0] if f.args else None)
        elif op == "ldarg.1":
            push(f.args[1] if len(f.args) > 1 else None)
        elif op == "ldarg.2":
            push(f.args[2] if len(f.args) > 2 else None)
        elif op == "ldarg.3":
            push(f.args[3] if len(f.args) > 3 else None)
        elif op == "ldloc" or op == "ldloc.s":
            push(f.locals[self.local_idx(operand)])
        elif op.startswith("ldloc."):
            push(f.locals[int(op[-1])])
        elif op == "stloc" or op == "stloc.s":
            _li = self.local_idx(operand)
            _v = pop()
            if getattr(f, "is_vm", False) and _li in (0xC, 7, 8, 0xB) and isinstance(_v, int):
                if not hasattr(self, "_clog"):
                    self._clog = []
                self._clog.append((f.pc, _li, _v))
            f.locals[_li] = _v
        elif op.startswith("stloc."):
            f.locals[int(op[-1])] = pop()
        elif op == "starg" or op == "starg.s":
            idx = int(operand.index) if hasattr(operand, "index") else int(operand)
            f.args[idx] = pop()
        elif op == "ldloca" or op == "ldloca.s":
            push(("loca", self.local_idx(operand)))
        elif op == "dup":
            v = pop(); push(v); push(v)
        elif op == "pop":
            pop()
        # ---- arithmetic ----
        elif op in ("add", "sub", "mul", "and", "or", "xor", "shl", "shr", "shr.un",
                    "div", "div.un", "rem", "rem.un"):
            b, a = pop(), pop()
            if op == "add":
                push(fit(a + b, a, b))
            elif op == "sub":
                r = fit(a - b, a, b)
                if r == -1 and {a, b} == {0, 1}:
                    import sys as _s
                    print(f"[sub 0-1 at {f.pc:#x} wide_ctx? stack_top_after]", file=_s.stderr)
                    self._neg1 = getattr(self, "_neg1", 0) + 1
                push(r)
            elif op == "mul":
                push(fit(a * b, a, b))
            elif op == "and":
                push(fit(a & b, a, b))
            elif op == "or":
                push(fit(a | b, a, b))
            elif op == "xor":
                push(fit(a ^ b, a, b))
            elif op == "shl":
                push(fit(a << (b & 31), a))
            elif op == "shr":
                push(to_i32(to_i32(a) >> (b & 31)))
            elif op == "shr.un":
                ua = a if a >= 0 else a & I32
                push(ua >> (b & 31) if ua <= 0xFFFFFFFF else (ua >> (b & 63)))
            elif op == "div":
                push(fit(int(a / b), a, b) if b else 0)
            elif op == "div.un":
                ua = a if a >= 0 else a & I32
                ub = b if b >= 0 else b & I32
                push(ua // ub if ub else 0)
            elif op == "rem":
                push(fit(a % b, a, b) if b else 0)
            elif op == "rem.un":
                ua = a if a >= 0 else a & I32
                ub = b if b >= 0 else b & I32
                push(ua % ub if ub else 0)
        elif op == "neg":
            push(to_i32(-pop()))
        elif op == "not":
            push(to_i32(~pop()))
        elif op in ("ceq",):
            b, a = pop(), pop()
            push(1 if a == b else 0)
        elif op in ("cgt", "cgt.un"):
            b, a = pop(), pop()
            if op == "cgt.un":
                push(1 if to_u32(a) > to_u32(b) else 0)
            else:
                push(1 if a > b else 0)
        elif op in ("clt", "clt.un"):
            b, a = pop(), pop()
            if op == "clt.un":
                push(1 if to_u32(a) < to_u32(b) else 0)
            else:
                push(1 if a < b else 0)
        # ---- conversions ----
        elif op == "conv.i4":
            v = pop()
            push(to_i32(int(v) if isinstance(v, float) else v))
        elif op == "conv.u4":
            v = pop()
            if isinstance(v, float):
                v = int(v)
            push(to_i32(v & I32))
        elif op == "conv.i8" or op == "conv.u8":
            v = pop()
            if isinstance(v, float):
                v = int(v)
            if op == "conv.u8":
                push(v if v >= 0 else (v + 0x10000000000000000))
            else:
                push(v)
        elif op == "conv.i1":
            push(to_i32((pop() & 0xFF) - 0x100 if pop() & 0x80 else pop() & 0xFF))
        elif op == "conv.u1":
            v = pop()
            push(to_i32((int(v) if isinstance(v, float) else v) & 0xFF))
        elif op == "conv.i2":
            v = pop() & 0xFFFF
            push(to_i32(v - 0x10000 if v & 0x8000 else v))
        elif op == "conv.u2":
            push(to_i32(pop() & 0xFFFF))
        elif op == "conv.i" or op == "conv.u":
            push(to_i32(pop()))
        elif op == "conv.r4" or op == "conv.r8":
            v = pop()
            push(float(to_u32(v) if isinstance(v, int) and v < 0 else v))
        elif op == "conv.r.un":
            v = pop()
            if isinstance(v, float):
                v = int(v)
            push(float(v if v >= 0 else (v & I32)))
        # ---- branches ----
        elif op in ("br", "br.s"):
            f.pc = self.resolve_target(f, operand); return
        elif op in ("brtrue", "brtrue.s"):
            v = pop()
            if v:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("brfalse", "brfalse.s"):
            v = pop()
            if not v:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("beq", "beq.s"):
            b, a = pop(), pop()
            if a == b:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("bne.un", "bne.un.s"):
            b, a = pop(), pop()
            if a != b:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("blt", "blt.s", "blt.un", "blt.un.s"):
            b, a = pop(), pop()
            cond = to_u32(a) < to_u32(b) if "un" in op else a < b
            if cond:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("bge", "bge.s", "bge.un", "bge.un.s"):
            b, a = pop(), pop()
            cond = to_u32(a) >= to_u32(b) if "un" in op else a >= b
            if cond:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("bgt", "bgt.s", "bgt.un", "bgt.un.s"):
            b, a = pop(), pop()
            cond = to_u32(a) > to_u32(b) if "un" in op else a > b
            if cond:
                f.pc = self.resolve_target(f, operand); return
        elif op in ("ble", "ble.s", "ble.un", "ble.un.s"):
            b, a = pop(), pop()
            cond = to_u32(a) <= to_u32(b) if "un" in op else a <= b
            if cond:
                f.pc = self.resolve_target(f, operand); return
        elif op == "switch":
            i = pop()
            if not hasattr(self, "_swlog"):
                self._swlog = []
            prev = f.pc
            self._swlog.append((i, operand[i] if 0 <= i < len(operand) else -1))
            targets = operand  # list of absolute offsets (dncil resolves)
            if 0 <= i < len(targets):
                f.pc = targets[i]; return
            # default = next
        elif op == "leave" or op == "leave.s":
            st.clear()
            f.pc = self.resolve_target(f, operand); return
        # ---- arrays ----
        elif op == "newarr":
            et = self.tok(operand) if not isinstance(operand, int) else operand
            n = pop()
            if n < 0:
                raise RuntimeError("newarr negative")
            # element type: resolve TypeRef idx
            elem = self.resolve_type_token(et)
            if elem and "Char" in elem:
                push([""] * n)
            else:
                push(bytearray(n))
        elif op == "ldlen":
            a = pop()
            push(to_i32(len(a)))
        elif op == "ldelem.u1":
            idx = pop(); arr = pop()
            push(arr[idx])
        elif op == "ldelem.i1":
            idx = pop(); arr = pop()
            v = arr[idx]
            push(to_i32(v - 256 if v & 0x80 else v))
        elif op == "ldelem.i4":
            idx = pop(); arr = pop()
            push(to_i32(arr[idx]))
        elif op == "ldelem.u4":
            idx = pop(); arr = pop()
            push(to_i32(arr[idx]))
        elif op == "ldelem.ref":
            idx = pop(); arr = pop()
            push(arr[idx])
        elif op == "ldelem":
            idx = pop(); arr = pop()
            push(arr[idx])
        elif op == "stelem.i1":
            val = pop(); idx = pop(); arr = pop()
            if isinstance(arr, bytearray) and len(arr) in (32, 16):
                if not hasattr(self, "_wlog"):
                    self._wlog = []
                self._wlog.append((len(arr), id(arr), f.pc, idx, val & 0xFF))
                if len(arr) == 32:
                    self._keyarr_id = id(arr)
            arr[idx] = val & 0xFF
        elif op == "stelem.i4":
            val = pop(); idx = pop(); arr = pop()
            arr[idx] = to_i32(val)
        elif op == "stelem.ref":
            val = pop(); idx = pop(); arr = pop()
            arr[idx] = val
        elif op == "stelem":
            val = pop(); idx = pop(); arr = pop()
            arr[idx] = val
        elif op == "ldelema":
            idx = pop(); arr = pop()
            push(("elema", arr, idx))
        # ---- fields ----
        elif op == "ldsfld":
            push(self.fields.get(self.fidx(self.tok(operand)), None))
        elif op == "stsfld":
            _fi = self.fidx(self.tok(operand))
            _v = pop()
            if _fi == 0x473:
                import sys as _s
                print(f"[TABLE WRITE] via {self.call_stack[-6:]} len={len(_v) if _v is not None else None} head={bytes(_v[:16]).hex() if _v else None}", file=_s.stderr)
            self.fields[_fi] = _v
        elif op == "ldfld":
            obj = pop()
            push(getattr(obj, self.fname(self.tok(operand)), None) if obj is not None else None)
        elif op == "stfld":
            val = pop(); obj = pop()
            if obj is not None:
                setattr(obj, self.fname(self.tok(operand)), val)
        elif op == "ldsflda":
            push(("sf", self.fidx(self.tok(operand))))
        # ---- calls ----
        elif op == "call":
            self.do_call(f, self.tok(operand), is_virtual=False)
        elif op == "callvirt":
            self.do_call(f, self.tok(operand), is_virtual=True)
        elif op == "newobj":
            self.do_newobj(f, self.tok(operand))
        elif op == "castclass":
            # permissive
            pass
        elif op == "isinst":
            v = pop()
            push(v)
        elif op == "box":
            pass
        elif op == "unbox":
            pass
        elif op == "ldtoken":
            push(self.tok(operand))
        elif op == "ret":
            if st:
                f.result = st[-1]
            else:
                f.result = None
            f.finished = True
            return
        elif op == "throw":
            ex = pop()
            raise ex if isinstance(ex, BaseException) else ExceptionObj(str(ex))
        elif op == "endfinally":
            pass
        elif op == "rethrow":
            raise RuntimeError("rethrow")
        else:
            raise NotImplementedError(f"opcode {op} at {f.pc:#x}")

        f.pc = nxt

    def next_off(self, f, ins):
        return f.next_map.get(ins.offset)

    def resolve_target(self, f, operand):
        # dncil gives absolute offsets for branches as ints already
        if isinstance(operand, int):
            return operand
        return operand

    def local_idx(self, operand):
        if hasattr(operand, "index"):
            return int(operand.index)
        return int(operand)

    def tok(self, operand):
        if hasattr(operand, "value"):
            v = operand.value
            if isinstance(v, int):
                return v
        if isinstance(operand, int):
            return operand
        # Token repr token(0x...)
        raise ValueError(f"bad token {operand!r}")

    def fidx(self, token):
        # 0x04xxxxxx Field
        return token & 0xFFFFFF

    def fname(self, token):
        idx = token & 0xFFFFFF
        try:
            return str(md.Field.rows[idx - 1].Name)
        except Exception:
            return f"field{idx}"

    def resolve_type_token(self, token):
        table = token >> 24
        idx = token & 0xFFFFFF
        try:
            if table == 0x01:
                return typeref_name(idx)
            if table == 0x02:
                r = md.TypeDef.rows[idx - 1]
                return str(r.TypeName)
        except Exception:
            pass
        return None

    def resolve_token_name(self, token):
        table = token >> 24
        idx = token & 0xFFFFFF
        if table == 0x0A:
            return memberref_name(idx)
        if table == 0x06:
            row = md.MethodDef.rows[idx - 1]
            # find owning type name
            return ("MethodDef", str(row.Name), idx)
        if table == 0x2B:
            r = md.MethodSpec.rows[idx - 1]
            m = r.Method.row if hasattr(r.Method, "row") else r.Method
            return ("MethodSpec", str(m.Name), None)
        return ("?", hex(token), None)

    def do_call(self, f, token, is_virtual):
        table = token >> 24
        idx = token & 0xFFFFFF
        st = f.stack

        if table == 0x06:  # MethodDef — interpret (wrappers, cipher, helpers)
            row = md.MethodDef.rows[idx - 1]
            name = str(row.Name)
            sig = bytes(row.Signature.value)
            hasthis = bool(sig[0] & 0x20)
            # count params from signature
            p = 1
            if sig[0] & 0x10:
                # generic — skip
                pass
            nparams = sig[p]; p += 1
            # skip return type
            _, p = self.skip_type(sig, p)
            params = []
            for _ in range(nparams):
                _, p = self.skip_type(sig, p)
            nargs = nparams + (1 if hasthis else 0)
            args = [st.pop() for _ in range(nargs)][::-1]
            if idx == 0x6B1:
                import sys as _s
                k, iv, data = args[1], args[2], args[3]
                print(f"[06B1] key={bytes(k).hex() if isinstance(k,(bytes,bytearray)) else type(k).__name__} id={id(k)} keyarr_id={getattr(self,'_keyarr_id',None)} same={id(k)==getattr(self,'_keyarr_id',None)}", file=_s.stderr)
                print(f"[06B1] iv ={bytes(iv).hex() if isinstance(iv,(bytes,bytearray)) else type(iv).__name__}", file=_s.stderr)
                print(f"[06B1] data len={len(data) if data is not None else None} head={bytes(data[:16]).hex() if data else None}", file=_s.stderr)
                _res = get_resource_bytes("PcwR4oMtmu2insTPq7.l15p7hYCxuxjEUmgDh")
                print(f"[06B1] data==resource: {bytes(data)==_res}", file=_s.stderr)
            self.call_stack.append(f"{idx:#x}:{name}")
            try:
                res = self.run_method(row, args)
            finally:
                self.call_stack.pop()
            if not self.is_void(sig):
                st.append(res)
            return

        if table == 0x0A:  # MemberRef
            tname, mname = memberref_name(idx)
            nargs = self.arity_for(tname, mname)
            if nargs is None:
                raise NotImplementedError(f"unknown arity {tname}::{mname}")
            inst = self.is_instance_member(tname, mname)
            total = nargs + (1 if inst else 0)
            popped = [st.pop() for _ in range(total)][::-1]
            if inst:
                recv, call_args = popped[0], popped[1:]
            else:
                recv, call_args = None, popped
            res = BCL.call(tname, mname, recv, call_args)
            voids = {"Write", "Close", "FlushFinalBlock", "set_Position", "set_Key",
                     "set_IV", "set_Mode", "Reverse", "InitializeArray", "SetValue",
                     "Enter", "Exit", "Remove", "Add", "set_Item"}
            if mname in voids or mname.startswith("set_"):
                return
            st.append(res)
            return

        if table == 0x2B:  # MethodSpec (generic method)
            r = md.MethodSpec.rows[idx - 1]
            m = r.Method.row if hasattr(r.Method, "row") else r.Method
            # First/Last etc — rarely used in our paths
            raise NotImplementedError(f"MethodSpec call {m.Name}")

        raise NotImplementedError(f"call token {token:#x}")

    def is_instance_member(self, tname, mname):
        instance_methods = {
            "get_BaseStream", "ReadBytes", "ReadInt32", "ReadUInt32", "Read", "Close",
            "get_Length", "set_Position", "Write", "ToArray", "set_Key", "set_IV",
            "set_Mode", "CreateDecryptor", "CreateEncryptor", "FlushFinalBlock",
            "TransformBlock", "ComputeHash", "get_EntryPoint", "GetName",
            "GetPublicKeyToken", "op_Equality", "op_Inequality", "GetMethod",
            "get_DeclaringType", "get_Assembly", "get_ManifestModule",
            "GetManifestResourceStream", "get_Module", "ResolveMethod",
            "get_IsStatic", "get_ReturnType", "MakeByRefType", "GetFields",
            "GetGenericArguments", "get_FieldType", "SetValue", "get_IsValueType",
            "GetParameters", "get_ParameterType", "StartsWith", "get_Length",
            "get_Chars", "GetBytes", "GetString", "GetType", "Remove", "Add",
            "get_Item", "get_Count", "get_PublicKeyToken",
        }
        # ctors are instance
        if mname == ".ctor":
            return True
        # Stream/BinaryReader/etc are instance except statics
        statics = {
            "op_Equality", "op_Inequality", "Reverse", "InitializeArray",
            "GetTypeFromHandle", "get_Unicode", "get_AllowOnlyFipsAlgorithms",
            "ToBase64String", "CreateInstance", "CreateDelegate", "get_OffsetToStringData",
            "op_Implicit", "get_Empty", "Concat", "FromBase64String",
        }
        if mname in statics:
            return False
        if mname in instance_methods:
            # op_Equality on MethodInfo/Assembly: instance-style (both args)
            if mname in ("op_Equality", "op_Inequality"):
                return False  # static-like: two args, no receiver
            return True
        # default: guess — types that are clearly static classes
        if tname in ("System.Array", "System.Convert", "System.Math", "System.Threading.Monitor",
                     "System.Runtime.CompilerServices.RuntimeHelpers", "System.Activator",
                     "System.Text.Encoding", "System.Reflection.AssemblyName"):
            if mname in ("Reverse", "InitializeArray", "ToBase64String", "CreateInstance",
                         "get_Unicode", "get_UTF8", "get_Default"):
                return False
        return True

    def arity_for(self, tname, mname):
        table = {
            "BinaryReader::.ctor": 1, "BinaryReader::get_BaseStream": 0,
            "BinaryReader::ReadBytes": 1, "BinaryReader::ReadInt32": 0,
            "BinaryReader::ReadUInt32": 0, "BinaryReader::Read": 3, "BinaryReader::Close": 0,
            "Stream::get_Length": 0, "Stream::set_Position": 1, "Stream::Read": 3,
            "Stream::Write": 3, "Stream::Close": 0,
            "MemoryStream::.ctor": None, "MemoryStream::ToArray": 0,
            "AesCryptoServiceProvider::.ctor": 0, "RijndaelManaged::.ctor": 0,
            "SymmetricAlgorithm::set_Key": 1, "SymmetricAlgorithm::set_IV": 1,
            "SymmetricAlgorithm::set_Mode": 1, "SymmetricAlgorithm::CreateDecryptor": None,
            "SymmetricAlgorithm::CreateEncryptor": None,
            "CryptoStream::.ctor": 3, "CryptoStream::FlushFinalBlock": 0, "CryptoStream::Write": 3,
            "HashAlgorithm::TransformBlock": 5, "HashAlgorithm::ComputeHash": None,
            "MD5CryptoServiceProvider::.ctor": 0, "CryptoConfig::get_AllowOnlyFipsAlgorithms": 0,
            "Array::Reverse": 1, "Convert::ToBase64String": None,
            "Encoding::GetBytes": 1, "Encoding::GetString": None, "Encoding::get_Unicode": 0,
            "String::StartsWith": 1, "String::get_Length": 0, "String::get_Chars": 1,
            "Assembly::GetName": 0, "AssemblyName::GetPublicKeyToken": 0,
            "Assembly::get_EntryPoint": 0, "MethodInfo::op_Equality": 2,
            "Assembly::op_Equality": 2, "Assembly::op_Inequality": 2,
            "StackFrame::.ctor": None, "StackFrame::GetMethod": 0,
            "MemberInfo::get_DeclaringType": 0, "MemberInfo::get_MetadataToken": 0,
            "Type::GetTypeFromHandle": 1, "Type::get_Assembly": 0,
            "Assembly::get_ManifestModule": 0, "Assembly::GetManifestResourceStream": 1,
            "Type::get_Module": 0, "Module::ResolveMethod": None,
            "MethodBase::get_IsStatic": 0, "MethodInfo::get_ReturnType": 0,
            "Type::MakeByRefType": 0, "Type::GetFields": None, "Type::GetGenericArguments": 0,
            "FieldInfo::get_FieldType": 0, "FieldInfo::SetValue": 2,
            "Type::get_IsValueType": 0, "ParameterInfo::get_ParameterType": 0,
            "MethodBase::GetParameters": 0, "Delegate::CreateDelegate": None,
            "RuntimeHelpers::InitializeArray": 2, "RuntimeHelpers::get_OffsetToStringData": 0,
            "Object::.ctor": 0, "Monitor::Enter": 2, "Monitor::Exit": 1,
            "Activator::CreateInstance": 1, "ObjectHandle::Unwrap": 1,
            "Exception::.ctor": None, "Object::GetType": 0,
            "Array::IndexOf": None, "Array::BinarySearch": None,
            "String::Concat": None, "String::op_Equality": 2,
            "List`1::Add": None, "List`1::get_Item": 1, "List`1::get_Count": 0,
            "SortedList::.ctor": 0, "Hashtable::.ctor": 0,
        }
        key = f"{tname.split('.')[-1] if '::' not in tname else tname}::{mname}"
        # tname from memberref is like System.IO.BinaryReader
        short = tname.split(".")[-1]
        k2 = f"{short}::{mname}"
        if k2 in table:
            v = table[k2]
            if v is None:
                return self.dynamic_arity(tname, mname)
            return v
        # unknown
        return self.dynamic_arity(tname, mname)

    def dynamic_arity(self, tname, mname):
        # conservative defaults
        if mname == ".ctor":
            return None
        if mname.startswith("set_"):
            return 1
        if mname.startswith("get_"):
            return 0
        raise NotImplementedError(f"dynamic arity {tname}::{mname}")

    def is_void(self, sig):
        # sig[0]=cc, sig[1]=nparams (maybe generic count first), then ret
        p = 1
        if sig[0] & 0x10:
            # generic arg count compressed
            _, p = self.skip_compressed(sig, p)
        nparams = sig[p]; p += 1
        return sig[p] == 0x01

    def skip_compressed(self, sig, p):
        b = sig[p]; p += 1
        if (b & 0x80) == 0:
            return b, p
        if (b & 0xC0) == 0x80:
            return ((b & 0x3F) << 8) | sig[p], p + 1
        return ((b & 0x1F) << 24) | (sig[p] << 16) | (sig[p + 1] << 8) | sig[p + 2], p + 3

    def skip_type(self, sig, p):
        et = sig[p]; p += 1
        if et in (0x0F, 0x10):  # ptr/byref
            return self.skip_type(sig, p)
        if et in (0x11, 0x12, 0x1F, 0x20):  # valuetype/class/cmod
            _, p = self.skip_compressed(sig, p)
            if et in (0x1F, 0x20):
                return self.skip_type(sig, p)
            return "t", p
        if et == 0x1D:  # szarray
            return self.skip_type(sig, p)
        if et == 0x15:  # genericinst
            p += 1  # kind
            _, p = self.skip_compressed(sig, p)
            n, p = self.skip_compressed(sig, p)
            for _ in range(n):
                _, p = self.skip_type(sig, p)
            return "t", p
        if et in (0x13, 0x1E):  # var/mvar
            _, p = self.skip_compressed(sig, p)
            return "t", p
        return "t", p

    def do_newobj(self, f, token):
        table = token >> 24
        idx = token & 0xFFFFFF
        st = f.stack
        if table == 0x0A:
            tname, mname = memberref_name(idx)
            short = tname.split(".")[-1]
            if mname == ".ctor":
                if short == "BinaryReader":
                    arg = st.pop()
                    st.append(BinaryReader(arg)); return
                if short == "MemoryStream":
                    # 0 or 1 args
                    # detect: peek — MemoryStream()0 args or MemoryStream(byte[])
                    # we can't peek types reliably; check top: if bytearray/bytes → 1 arg
                    if st and isinstance(st[-1], (bytes, bytearray, list)):
                        st.append(MemStream(bytes(st.pop()))); return
                    st.append(MemStream()); return
                if short in ("AesCryptoServiceProvider", "RijndaelManaged"):
                    st.append(AesObj()); return
                if short == "CryptoStream":
                    mode = st.pop(); tr = st.pop(); und = st.pop()
                    st.append(CryptoStreamObj(und, tr, mode)); return
                if short == "StackFrame":
                    st.append(StackFrameObj()); return
                if short == "Exception":
                    msg = st.pop() if st else ""
                    st.append(ExceptionObj(str(msg))); return
                if short == "MD5CryptoServiceProvider":
                    st.append(Dyn()); return
                if short in ("SortedList",):
                    st.append({}); return
                if short in ("Hashtable",):
                    st.append({}); return
                if short == "Object":
                    st.append(Dyn()); return
                # generic fallback: pop known-arity? raise
                raise NotImplementedError(f"newobj {tname}::{mname}")
        if table == 0x06:  # MethodDef .ctor (our types)
            row = md.MethodDef.rows[idx - 1]
            sig = bytes(row.Signature.value)
            hasthis = bool(sig[0] & 0x20)
            p = 1
            if sig[0] & 0x10:
                _, p = self.skip_compressed(sig, p)
            nparams = sig[p]; p += 1
            _, p = self.skip_type(sig, p)
            for _ in range(nparams):
                _, p = self.skip_type(sig, p)
            popped = [st.pop() for _ in range(nparams)][::-1]
            if hasthis:
                obj = Dyn()
                self.run_method(row, [obj] + popped)
                st.append(obj)
            else:
                res = self.run_method(row, popped)
                st.append(res if res is not None else Dyn())
            return
        raise NotImplementedError(f"newobj {token:#x}")

    _owner_cache = {}

    def owner_of_method(self, midx):
        if midx in self._owner_cache:
            return self._owner_cache[midx]
        for td in md.TypeDef.rows:
            for mi in td.MethodList:
                if mi.row_index == midx:
                    self._owner_cache[midx] = str(td.TypeName)
                    return self._owner_cache[midx]
        return None


def extract_strings(table):
    """Table layout: at offset id: int32 (length, later replaced by cache index),
    payload UTF-16LE at id+4."""
    out = []
    n = len(table)
    # scan every 2 bytes for plausible [len][utf16 payload]
    off = 0
    seen = set()
    while off + 4 <= n:
        ln = struct.unpack_from("<i", table, off)[0]
        if 2 <= ln <= 2000 and ln % 2 == 0 and off + 4 + ln <= n:
            payload = bytes(table[off + 4:off + 4 + ln])
            try:
                s = payload.decode("utf-16-le")
            except Exception:
                off += 2; continue
            if s.isprintable() and any(c.isalpha() for c in s):
                if s not in seen:
                    seen.add(s)
                    out.append((off, s))
                off += 4 + ln
                continue
        off += 2
    return out


def main():
    interp = Interp()
    # find VM method
    vm_row = None
    for td in md.TypeDef.rows:
        if str(td.TypeName) == "zEbH14FTd6Hg7uAcT8B":
            for mi in td.MethodList:
                if str(mi.row.Name) == "a253xoPyrm":
                    vm_row = mi.row
    assert vm_row is not None

    # args: (Stream resource, int id) — id used only for branch; pass -1? pass 0
    # eS535wKabC passes the real id; try id=0 first (any id != -1 follows same branch)
    resource = get_resource_bytes("PcwR4oMtmu2insTPq7.l15p7hYCxuxjEUmgDh")
    print(f"resource: {len(resource)} bytes", file=sys.stderr)
    stream = StreamObj(resource)

    interp.run_method(vm_row, [stream, 0])

    table = interp.fields.get(0x473)
    if table is None:
        print("table not produced", file=sys.stderr)
        sys.exit(1)
    print(f"table: {len(table)} bytes", file=sys.stderr)
    open("/tmp/table.bin", "wb").write(bytes(table))

    if hasattr(interp, "_wlog"):
        with open("/tmp/wlog.txt", "w") as fh:
            for ln, aid, pc, idx, val in interp._wlog:
                fh.write(f"{ln} {aid} {pc:#06x} {idx} {val}\n")
        import collections
        by_arr = collections.Counter((ln, aid) for ln, aid, *_ in interp._wlog)
        print(f"arrays: {dict(by_arr)}", file=sys.stderr)
        print(f"wlog: {len(interp._wlog)} writes", file=sys.stderr)
    if hasattr(interp, "_clog"):
        with open("/tmp/clog.txt", "w") as fh:
            for pc, li, v in interp._clog:
                fh.write(f"{pc:#06x} {li} {v}\n")
        print(f"clog: {len(interp._clog)}", file=sys.stderr)
    if hasattr(interp, "_swlog"):
        with open("/tmp/swlog.txt", "w") as fh:
            for i, tgt in interp._swlog:
                fh.write(f"{i} {tgt}\n")
        print(f"swlog: {len(interp._swlog)} states", file=sys.stderr)
    strings = extract_strings(table)
    print(f"found {len(strings)} strings", file=sys.stderr)
    for off, s in strings:
        print(f"{off}\t{s}")


if __name__ == "__main__":
    main()
