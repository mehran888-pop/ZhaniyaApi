#!/usr/bin/env python3
"""Minimal ECMA-335 IL disassembler for ZhaniyaApi.dll (inspect decryptor methods)."""
import struct
import sys
import dnfile

# opcode -> (name, operand_kind)
# operand kinds: None, 'i1','u1','i2','i4','i8','r4','r8','tok','br1','br4','switch'
OPS = {}
def add(code, name, kind=None):
    OPS[code] = (name, kind)

# 1-byte opcodes
single = {
0x00:('nop',None),0x01:('break',None),0x02:('ldarg.0',None),0x03:('ldarg.1',None),
0x04:('ldarg.2',None),0x05:('ldarg.3',None),0x06:('ldloc.0',None),0x07:('ldloc.1',None),
0x08:('ldloc.2',None),0x09:('ldloc.3',None),0x0A:('stloc.0',None),0x0B:('stloc.1',None),
0x0C:('stloc.2',None),0x0D:('stloc.3',None),0x0E:('ldarg.s','u1'),0x0F:('ldarga.s','u1'),
0x10:('starg.s','u1'),0x11:('ldloc.s','u1'),0x12:('ldloca.s','u1'),0x13:('stloc.s','u1'),
0x14:('ldnull',None),0x15:('ldc.i4.m1',None),0x16:('ldc.i4.0',None),0x17:('ldc.i4.1',None),
0x18:('ldc.i4.2',None),0x19:('ldc.i4.3',None),0x1A:('ldc.i4.4',None),0x1B:('ldc.i4.5',None),
0x1C:('ldc.i4.6',None),0x1D:('ldc.i4.7',None),0x1E:('ldc.i4.8',None),0x1F:('ldc.i4.s','i1'),
0x20:('ldc.i4','i4'),0x21:('ldc.i8','i8'),0x22:('ldc.r4','r4'),0x23:('ldc.r8','r8'),
0x25:('dup',None),0x26:('pop',None),0x27:('jmp','tok'),0x28:('call','tok'),
0x29:('calli','tok'),0x2A:('ret',None),0x2B:('br.s','br1'),0x2C:('brfalse.s','br1'),
0x2D:('brtrue.s','br1'),0x2E:('beq.s','br1'),0x2F:('bne.un.s','br1'),0x30:('blt.s','br1'),
0x31:('bge.s','br1'),0x32:('bgt.s','br1'),0x33:('ble.s','br1'),0x34:('brfalse.s','br1'),
0x34:('br','br4'),0x35:('brfalse','br4'),0x36:('brtrue','br4'),0x37:('beq','br4'),
0x38:('bne.un','br4'),0x39:('blt','br4'),0x3A:('bge','br4'),0x3B:('bgt','br4'),
0x3C:('ble','br4'),0x3D:('bge.un','br4'),0x3E:('bgt.un','br4'),0x3F:('blt.un','br4'),
0x40:('ble.un','br4'),0x42:('ldind.i1',None),0x43:('ldind.u1',None),0x44:('ldind.i2',None),
0x45:('ldind.u2',None),0x46:('ldind.i4',None),0x47:('ldind.u4',None),0x48:('ldind.i8',None),
0x49:('ldind.i',None),0x4A:('ldind.r4',None),0x4B:('ldind.r8',None),0x4C:('ldind.ref',None),
0x4D:('stind.ref',None),0x4E:('stind.i1',None),0x4F:('stind.i2',None),0x50:('stind.i4',None),
0x51:('stind.i8',None),0x52:('stind.r4',None),0x53:('stind.r8',None),0x54:('stind.i',None),
0x55:('add',None),0x56:('sub',None),0x57:('mul',None),0x58:('div',None),0x59:('div.un',None),
0x5A:('rem',None),0x5B:('rem.un',None),0x5C:('and',None),0x5D:('or',None),0x5E:('xor',None),
0x5F:('shl',None),0x60:('shr',None),0x61:('shr.un',None),0x62:('neg',None),0x63:('not',None),
0x64:('conv.i1',None),0x65:('conv.i2',None),0x66:('conv.i4',None),0x67:('conv.i8',None),
0x68:('conv.r4',None),0x69:('conv.r8',None),0x6A:('conv.u4',None),0x6B:('conv.u8',None),
0x6C:('callvirt','tok'),0x6D:('cpobj','tok'),0x6E:('ldobj','tok'),0x6F:('ldstr','tok'),
0x70:('newobj','tok'),0x71:('castclass','tok'),0x72:('isinst','tok'),0x73:('unbox',None),
0x74:('throw',None),0x75:('ldfld','tok'),0x76:('ldflda','tok'),0x77:('stfld','tok'),
0x78:('ldsfld','tok'),0x79:('ldsflda','tok'),0x7A:('stsfld','tok'),0x7B:('stobj','tok'),
0x7C:('conv.ovf.i1.un',None),0x7D:('conv.ovf.i2.un',None),0x7E:('conv.ovf.i4.un',None),
0x7F:('conv.ovf.i8.un',None),0x80:('conv.ovf.u1.un',None),0x81:('conv.ovf.u2.un',None),
0x82:('conv.ovf.u4.un',None),0x83:('conv.ovf.u8.un',None),0x84:('conv.ovf.i.un',None),
0x85:('conv.ovf.u.un',None),0x86:('box',None),0x87:('newarr','tok'),0x88:('ldlen',None),
0x89:('ldelema','tok'),0x8A:('ldelem.i1',None),0x8B:('ldelem.u1',None),0x8C:('ldelem.i2',None),
0x8D:('ldelem.u2',None),0x8E:('ldelem.i4',None),0x8F:('ldelem.u4',None),0x90:('ldelem.i8',None),
0x91:('ldelem.i',None),0x92:('ldelem.r4',None),0x93:('ldelem.r8',None),0x94:('ldelem.ref',None),
0x95:('stelem.i',None),0x96:('stelem.i1',None),0x97:('stelem.i2',None),0x98:('stelem.i4',None),
0x99:('stelem.i8',None),0x9A:('stelem.r4',None),0x9B:('stelem.r8',None),0x9C:('stelem.ref',None),
0x9D:('ldelem',None),0x9E:('stelem',None),0x9F:('unbox.any',None),0xA2:('unbox.any',None),
0xA3:('unbox.any','tok'),0xA4:('box','tok'),0xA5:('newarr','tok'),0xD0:('ldftn','tok'),
0xD1:('ldvirtftn','tok'),0xD2:('ldarg','u2'),0xD3:('ldarga','u2'),0xD4:('starg','u2'),
0xD5:('ldloc','u2'),0xD6:('ldloca','u2'),0xD7:('stloc','u2'),0xD8:('localloc',None),
0xD9:('endfilter',None),0xDA:('unaligned.','u1'),0xDB:('volatile.',None),0xDC:('tail.',None),
0xDD:('initobj','tok'),0xDE:('constrained.','tok'),0xDF:('cpblk',None),0xE0:('initblk',None),
0xE1:('rethrow',None),0xE3:('sizeof','tok'),0xE4:('refanytype',None),0xE5:('readonly.',None),
}
# fix known corrections: 0x6F=ldstr? NO — correct ECMA:
# 0x6C callvirt, 0x6D cpobj, 0x6E ldobj, 0x6F ldstr, 0x70 newobj, 0x71 castclass, 0x72 isinst,
# 0x73 unbox, 0x74 throw, 0x75 ldfld, 0x76 ldflda, 0x77 stfld, 0x78 ldsfld, 0x79 ldsflda, 0x7A stsfld,
# 0x7B stobj, 0x7C conv.ovf.i1.un ... 0x82 box? Actually 0x82 = conv.ovf.u4.un, 0x86 = box? Let's use canonical:
single.update({
0x6F:('ldstr','tok'), 0x70:('newobj','tok'), 0x71:('castclass','tok'), 0x72:('isinst','tok'),
0x73:('unbox','tok'), 0x74:('throw',None), 0x75:('ldfld','tok'), 0x76:('ldflda','tok'),
0x77:('stfld','tok'), 0x78:('ldsfld','tok'), 0x79:('ldsflda','tok'), 0x7A:('stsfld','tok'),
0x7B:('stobj','tok'),
0x8C:('box','tok'), 0x8D:('newarr','tok'), 0x8E:('ldlen',None), 0x8F:('ldelema','tok'),
0x9A:('ldelem.i4',None), 0x9C:('ldelem.ref',None), 0xA2:('stelem.ref',None),
0xA3:('ldelem','tok'), 0xA4:('stelem','tok'), 0xA5:('unbox.any','tok'),
0xD0:('ldtoken','tok'),
})
for k,v in single.items():
    OPS[k]=v

FE = {
0x00:('arglist',None),0x01:('ceq',None),0x02:('cgt',None),0x03:('cgt.un',None),
0x04:('clt',None),0x05:('clt.un',None),0x06:('ldftn','tok'),0x07:('ldvirtftn','tok'),
0x09:('ldarg','u2'),0x0A:('ldarga','u2'),0x0B:('starg','u2'),0x0C:('ldloc','u2'),
0x0D:('ldloca','u2'),0x0E:('stloc','u2'),0x0F:('localloc',None),0x11:('endfilter',None),
0x12:('unaligned.','u1'),0x13:('volatile.',None),0x14:('tail.',None),0x15:('initobj','tok'),
0x16:('constrained.','tok'),0x17:('cpblk',None),0x18:('initblk',None),0x1A:('rethrow',None),
0x1C:('sizeof','tok'),0x1D:('refanytype',None),0x1E:('readonly.',None),
}

def read_il(pe, m):
    rva = m.Rva
    if not rva:
        return None
    data = pe.get_data(rva, 4096)
    b0 = data[0]
    if (b0 & 3) == 2:  # tiny
        size = b0 >> 2
        return data[1:1+size]
    flags_size = struct.unpack('<H', data[0:2])[0]
    hdr = (flags_size >> 12) * 4
    if hdr < 12:
        hdr = 12
    codesize = struct.unpack('<I', data[4:8])[0]
    return data[hdr:hdr+codesize]

def disasm(code, resolver=None):
    out = []
    p = 0
    while p < len(code):
        ip = p
        op = code[p]; p += 1
        if op == 0xFE:
            op2 = code[p]; p += 1
            name, kind = FE.get(op2, (f'fe.{op2:02x}', None))
        else:
            name, kind = OPS.get(op, (f'op_{op:02x}', None))
        operand = None
        if kind == 'u1':
            operand = code[p]; p += 1
        elif kind == 'i1':
            operand = struct.unpack('<b', code[p:p+1])[0]; p += 1
        elif kind == 'u2':
            operand = struct.unpack('<H', code[p:p+2])[0]; p += 2
        elif kind == 'i4':
            operand = struct.unpack('<i', code[p:p+4])[0]; p += 4
        elif kind == 'i8':
            operand = struct.unpack('<q', code[p:p+8])[0]; p += 8
        elif kind == 'r4':
            operand = struct.unpack('<f', code[p:p+4])[0]; p += 4
        elif kind == 'r8':
            operand = struct.unpack('<d', code[p:p+8])[0]; p += 8
        elif kind == 'tok':
            tok = struct.unpack('<I', code[p:p+4])[0]; p += 4
            operand = resolver(tok) if resolver else hex(tok)
        elif kind == 'br1':
            off = struct.unpack('<b', code[p:p+1])[0]; p += 1
            operand = f'IL_{p+off:04x}'
        elif kind == 'br4':
            off = struct.unpack('<i', code[p:p+4])[0]; p += 4
            operand = f'IL_{p+off:04x}'
        out.append((ip, name, operand))
    return out


def make_resolver(pe, md):
    US = pe.net.user_strings
    def resolve(tok):
        table = tok >> 24
        idx = tok & 0xFFFFFF
        try:
            if table == 0x70:
                v = US.get(idx)
                return f'ldstr {str(v)!r}' if v is not None else f'ldstr <none@{idx:#x}>'
            if table == 0x0A:
                row = md.MemberRef.rows[idx-1]
                try:
                    clsrow = row.Class.row
                    cn = str(getattr(clsrow, 'TypeName', None) or getattr(clsrow, 'Name', '?'))
                except Exception:
                    cn = '?'
                return f'{cn}::{row.Name}'
            if table == 0x06:
                return f'MethodDef::{md.MethodDef.rows[idx-1].Name}'
            if table == 0x04:
                row = md.Field.rows[idx-1]
                return f'Field::{row.Name}'
            if table == 0x01:
                row = md.TypeRef.rows[idx-1]
                return f'TypeRef::{row.TypeNamespace}.{row.TypeName}'
            if table == 0x02:
                row = md.TypeDef.rows[idx-1]
                return f'TypeDef::{row.TypeName}'
            if table == 0x2B:
                row = md.MethodSpec.rows[idx-1]
                m = row.Method.row if hasattr(row.Method, 'row') else row.Method
                return f'MethodSpec::{m.Name}'
        except Exception as e:
            return f'{tok:#x}({e})'
        return hex(tok)
    return resolve


def main():
    dll = sys.argv[1] if len(sys.argv) > 1 else 'bin/ZhaniyaApi.dll'
    wanted = sys.argv[2:] or ['eS535wKabC']
    pe = dnfile.dnPE(dll)
    md = pe.net.mdtables
    resolve = make_resolver(pe, md)
    for td in md.TypeDef.rows:
        for mi in td.MethodList:
            m = mi.row
            if str(m.Name) in wanted:
                code = read_il(pe, m)
                print(f'=== {td.TypeName}::{m.Name} ({len(code) if code else 0} bytes)')
                if not code:
                    continue
                for ip, name, operand in disasm(code, resolve):
                    print(f'  IL_{ip:04x}: {name} {operand if operand is not None else ""}')


if __name__ == '__main__':
    main()
