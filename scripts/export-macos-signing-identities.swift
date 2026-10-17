// Export only PHPAML's two Developer ID identities, encrypted with a password
// received over stdin. Never print private key material or its password.
import Foundation
import Security

func checked(_ status: OSStatus) throws {
    if status != errSecSuccess {
        throw NSError(domain: NSOSStatusErrorDomain, code: Int(status))
    }
}

do {
    guard CommandLine.arguments.count == 3, let password = readLine(), !password.isEmpty else {
        throw NSError(domain: "Expected keychain and output directory; password on stdin", code: 1)
    }
    var keychain: SecKeychain?
    try checked(SecKeychainOpen(CommandLine.arguments[1], &keychain))
    var result: CFTypeRef?
    try checked(SecItemCopyMatching([
        kSecClass: kSecClassIdentity,
        kSecReturnRef: true,
        kSecMatchLimit: kSecMatchLimitAll,
        kSecMatchSearchList: [keychain!]
    ] as CFDictionary, &result))
    let identities = result as? [SecIdentity] ?? []
    for (kind, title) in [("APPLICATION", "Application"), ("INSTALLER", "Installer")] {
        let name = "Developer ID \(title): Andre Mulaja (D5798DRJ9K)"
        let matches = identities.filter { identity in
            var certificate: SecCertificate?
            return SecIdentityCopyCertificate(identity, &certificate) == errSecSuccess
                && (SecCertificateCopySubjectSummary(certificate!) as String?) == name
        }
        guard matches.count == 1 else {
            throw NSError(domain: "Expected exactly one identity: \(name)", code: 1)
        }
        var parameters = SecItemImportExportKeyParameters()
        parameters.version = UInt32(SEC_KEY_IMPORT_EXPORT_PARAMS_VERSION)
        let phrase = password as CFString
        parameters.passphrase = Unmanaged.passUnretained(phrase)
        var exported: CFData?
        try checked(SecItemExport(matches[0], .formatPKCS12, [], &parameters, &exported))
        let target = URL(fileURLWithPath: CommandLine.arguments[2]).appendingPathComponent(kind + ".p12")
        try (exported! as Data).write(to: target, options: .atomic)
        try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: target.path)
        print("Encrypted \(title) identity prepared.")
    }
} catch {
    FileHandle.standardError.write(Data("Identity export failed: \(error)\n".utf8))
    exit(1)
}
