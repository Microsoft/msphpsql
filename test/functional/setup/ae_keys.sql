/* DROP Column Encryption Keys first, Column Master Keys cannot be dropped until no CEKs depend on them */
IF EXISTS (SELECT * FROM sys.column_encryption_keys WHERE [name] LIKE '%AEColumnKey%' OR [name] LIKE '%-win-%')
BEGIN
DROP COLUMN ENCRYPTION KEY [AEColumnKey]
DROP COLUMN ENCRYPTION KEY [CEK-win-enclave]
DROP COLUMN ENCRYPTION KEY [CEK-win-enclave2]
DROP COLUMN ENCRYPTION KEY [CEK-win-noenclave]
DROP COLUMN ENCRYPTION KEY [CEK-win-noenclave2]
END
GO

/* Can finally drop Column Master Keys after the Column Encryption Keys are dropped */
IF EXISTS (SELECT * FROM sys.column_master_keys WHERE [name] LIKE '%AEMasterKey%' OR [name] LIKE '%-win-%')
BEGIN
DROP COLUMN MASTER KEY [AEMasterKey]
DROP COLUMN MASTER KEY [CMK-win-enclave]
DROP COLUMN MASTER KEY [CMK-win-noenclave]
END
GO

/* Create the Column Master Keys */
/* AKVMasterKey is a non-enclave enabled key for AE v1 testing */
/* The enclave-enabled master key requires an ENCLAVE_COMPUTATIONS clause */
CREATE COLUMN MASTER KEY [AEMasterKey]
WITH
(
    KEY_STORE_PROVIDER_NAME = N'MSSQL_CERTIFICATE_STORE',
    KEY_PATH = N'CurrentUser/my/237F94738E7F5214D8588006C2269DBC6B370816'
)
GO

/* The enclave-enabled master key requires an ENCLAVE_COMPUTATIONS clause */
CREATE COLUMN MASTER KEY [CMK-win-enclave]
WITH
(
    KEY_STORE_PROVIDER_NAME = N'MSSQL_CERTIFICATE_STORE',
    KEY_PATH = N'CurrentUser/My/D9C0572FA54B221D6591C473BAEA53FE61AAC854',
    ENCLAVE_COMPUTATIONS (SIGNATURE = 0xA1150DE565E9C132D2AAB8FF8B228EAA8DA804F250B5B422874CB608A3B274DDE523E71B655A3EFC6C3018B632701E9205BAD80C178614E1FE821C6807B0E70BCF11168FC4B202638905C5F016EDBADACA23C696B79772C56825F36EB8C0366B130C91D85362E560C9D2FDD20DCAE99619256045CA2725DEC9E0C115CAEB9EA686CCB0DE0D53D2056C01752B17B634FC6DBB51EA043F607349489722DB8A086CBC876649284A8352822DD22B328E7BA3D671CCDF54CDAAF61DFD6AF2EAAC14E03897324234AB103C45AB48131C1CD19040782359FC920A0AF61BA9842ADFB76C3196CBC6EB9C0A679926ED63E092B7C8643232C97A64C7F918104C210787A56F)
)

CREATE COLUMN MASTER KEY [CMK-win-noenclave]
WITH
(
    KEY_STORE_PROVIDER_NAME = N'MSSQL_CERTIFICATE_STORE',
    KEY_PATH = N'CurrentUser/My/D9C0572FA54B221D6591C473BAEA53FE61AAC854'
)

/* Now we can create the Column Encryption Keys */
/* ENCRYPTED_VALUE is generated by SSMS and it is always the same if the same Certificate is imported */
CREATE COLUMN ENCRYPTION KEY [AEColumnKey]
WITH VALUES
(
    COLUMN_MASTER_KEY = [AEMasterKey],
    ALGORITHM = 'RSA_OAEP',
    ENCRYPTED_VALUE = 0x016E000001630075007200720065006E00740075007300650072002F006D0079002F00320033003700660039003400370033003800650037006600350032003100340064003800350038003800300030003600630032003200360039006400620063003600620033003700300038003100360039DE2397A08F6313E7820D75382D8469BE1C8F3CD47E3240A5A6D6F82D322F6EB1B103C9C47999A69FFB164D37E7891F60FFDB04ADEADEB990BE88AE488CAFB8774442DF909D2EF8BB5961A5C11B85BA7903E0E453B27B49CE0A30D14FF4F412B5737850A4C564B44C744E690E78FAECF007F9005E3E0FB4F8D6C13B016A6393B84BB3F83FEED397C4E003FF8C5BBDDC1F6156349A8B40EDC26398C9A03920DD81B9197BC83A7378F79ECB430A04B4CFDF3878B0219BB629F5B5BF3C2359A7498AD9A6F5D63EF15E060CDB10A65E6BF059C7A32237F0D9E00C8AC632CCDD68230774477D4F2E411A0E4D9B351E8BAA87793E64456370D91D4420B5FD9A252F6D9178AE3DD02E1ED57B7F7008114272419F505CBCEB109715A6C4331DEEB73653990A7140D7F83089B445C59E4858809D139658DC8B2781CB27A749F1CE349DC43238E1FBEAE0155BF2DBFEF6AFD9FD2BD1D14CEF9AC125523FD1120488F24416679A6041184A2719B0FC32B6C393FF64D353A3FA9BC4FA23DFDD999B0771A547B561D72B92A0B2BB8B266BC25191F2A0E2F8D93648F8750308DCD79BE55A2F8D5FBE9285265BEA66173CD5F5F21C22CC933AE2147F46D22BFF329F6A712B3D19A6488DDEB6FDAA5B136B29ADB0BA6B6D1FD6FBA5D6A14F76491CB000FEE4769D5B268A3BF50EA3FBA713040944558EDE99D38A5828E07B05236A4475DA27915E
)
GO

/* There are two enclave enabled keys and two non-enclave enabled keys to test the case where a user
   tries to reencrypt a table from one enclave enabled key to another enclave enabled key, or from a
   non-enclave key to another non-enclave key */
CREATE COLUMN ENCRYPTION KEY [CEK-win-enclave]
WITH VALUES
(
    COLUMN_MASTER_KEY = [CMK-win-enclave],
    ALGORITHM = 'RSA_OAEP',
    ENCRYPTED_VALUE = 0x016E000001630075007200720065006E00740075007300650072002F006D0079002F0064003900630030003500370032006600610035003400620032003200310064003600350039003100630034003700330062006100650061003500330066006500360031006100610063003800350034007382EDDDE3FFCE076D5715B6BBBD22EA64E665899BEFAAD5B329F218EE30BE9F789EB98717B6FD9E50AE496AC9FEED962B23442D4FD3FBFEC9C9B65F40A3BCEC7CFAC198F4CAEE8A255F67988289EF050F9F75D0287F3DF9A9FDA0C674E48DF2CB13298AAAD039930DD909EEE71682CC8A90202D3F2A1F1037BB20B1954C8B6A11F05D104CA9DAF1561C6B2F9DBB08BCE17244157B751C02FC1730E387F372C31327F2834D19AF626D0B46B152615F05FA2F3566350312CDE6DE1160B3C1D0FD35FAF13891C04711DF184DA501AA51D16BF009EA71A2D28E201804C6F8F9100E90234923B2713EA7988861FBA4E292E5518FFC02CCBD2513EDA871F6E03ECDDD309619557277C10A07906E55BA3F59A6A18834B4CD5185DA4B4574A18B8B1AC53A2C36B033D7A72443F1438E76E37306A1F92AC30BC751F6D7ED1633FEE807440E1D6096C53C5E3E33828C9C59E8761E5BAD341C6D9E2BD1F2B5C3992666620CAA38C4645C154976EF62AE80161A9F7700C96875A72995E1C585918B28F65060F1B8B96417328F6DEDFCA79ED9F01EAB19FF4E3163F9963BA26E9B58031A04320CC73702A6ED438513E0F8ABA1966B53114038CC587050F90D9CD0F9E26CA9749723ABA85CF31F963A5E85E04993B2B2869725E734BE8FCFD30A801825582730B49C00A2058C02D3312D6D8E82078FF4F77C5FF9CE6E9D140F1A4517635AB784
)

CREATE COLUMN ENCRYPTION KEY [CEK-win-enclave2]
WITH VALUES
(
    COLUMN_MASTER_KEY = [CMK-win-enclave],
    ALGORITHM = 'RSA_OAEP',
    ENCRYPTED_VALUE = 0x016E000001630075007200720065006E00740075007300650072002F006D0079002F0064003900630030003500370032006600610035003400620032003200310064003600350039003100630034003700330062006100650061003500330066006500360031006100610063003800350034006B4D40ABF0975AF7C5CA7D1F4345DE437318556F5A2380DCFE4AB792DC3A424EABC80EA24EE850FACD94F04809C8B32674C6FF2D966FA7F9F9E522990E5F5011515BA4B7EF3603619D8A4BF46AA9B769A8A4417462C4B0303F995F04964A2E328A503D87CD1AB85ECFCB8241D0C815540989DC33E58EDCCBAFF0753E196813E3FCCC5A3C9E4277DD528AE276F1F795973A4DF8D1BB3B1F405B5F35A6A583F0BB86BAD7FCADC1FCF6B14B602890109360FAB67D6A27DE542AE87784C40FEB9071AC34C4C40C92A6C153A4A38B6DA3AD48ED39E32D6D161ACE7EFE516B414139A831D878C13FF178649823C4EFDC8E5DB4C02F2147CC76965C01C2F3624EB809FD4F5C2E291056077B1ABEFF1F5001C1F4248704C7C70CF63DA1EBC2FEC4A3DF919BA4F6B465819BC4587599C2E7499CDE62D7C335CE7BBCFC72242A8F41C1B5C94DEB0A9AF49B723759A8CD9751EE70DDEBAFA1957382287F621790543841EBCCA0007BA030CAF29E9FBF8CEB4FEC88673F47B5EC3B5F759BBDD8ED2EAF572711D78286E4294B89FF6EBFEE4968B4596AF3B5C34985F28E886F6C211F385326F10ED62602007589FC494372902FB32B0E3D67A8C64F43A87B06EE9F2CF074EB6F3EC7A431733EDA8745051B7A4AA4C020797A9492E6A3BA643D031E491497BF17539993871085AC249D0AD82203CD442F69D6C686D26F4D17BA46B69D3CB7E395
)

CREATE COLUMN ENCRYPTION KEY [CEK-win-noenclave]
WITH VALUES
(
    COLUMN_MASTER_KEY = [CMK-win-noenclave],
    ALGORITHM = 'RSA_OAEP',
    ENCRYPTED_VALUE = 0x016E000001630075007200720065006E00740075007300650072002F006D0079002F00640039006300300035003700320066006100350034006200320032003100640036003500390031006300340037003300620061006500610035003300660065003600310061006100630038003500340042DC7A3AAAD184E01288C0913EFB6FEC6167CD8EA08A5F46ADCCC34D3AA6A1BDDDA15EA3DD219ED8795AB05C0111E48EA35A82ADDF2A206FACBBF4FD73D01C004DF627012D3950FEBCA4BBEDBDF97BA77033728D8873BA81E1C7BDCBE04BB3AA7EB42A1EDDBEF9B1CA9477ADA33F76711FEDF782CA1BD3C0104FDEB9E0D66DFCEC7D3C236906481B44F04457549658635322447742FB00B6D6F36A7CFCC56BB39F7280736BC25FD499F9CBA2F63CE11D53E536FD4A266929E06CF2BDBAF229894A77EDE140323B674ECF28C58C3E0B6C2E9407AD1A26776CB55D68B8286F64787CE5A468CFA27295D6069EFA5D65CD9A04602E861F4504F2611AAE6A8ADE33038A2BECE8BD7CF5B48567C217E324F11935C552FD25FE1FEFB152684BD1B3F8EB70EC9F6439340CE82CD8E74DD5986A6C4F9E8336ED4AC804FAD800A3EA324F78DCE37832035C3DC92782A06150916D01322A80767D1A36D7A8D9BCF6727DCE6AC67A168FA8B8B5032E60DCB178B21A860F2D98BE09DA9BA5DCCBD0D339369FF3C50C7993463372CF5B1DA9FAA12CD16E76F5961C01EADC5804C7F22227E2095BAD0F90A47B6330B1B43407E01DE5B61CEBD542A93797428AD84376E9362EADE6DDD103B9EC96E616A2ECED7D1D665B5B872E77FC024AD92AB4A8335D12D41BDD152790E87590798C1005956F9F92D4DD0C1C9852D147F7CB55B3224DE8EF593F
)

CREATE COLUMN ENCRYPTION KEY [CEK-win-noenclave2]
WITH VALUES
(
    COLUMN_MASTER_KEY = [CMK-win-noenclave],
    ALGORITHM = 'RSA_OAEP',
    ENCRYPTED_VALUE = 0x016E000001630075007200720065006E00740075007300650072002F006D0079002F0064003900630030003500370032006600610035003400620032003200310064003600350039003100630034003700330062006100650061003500330066006500360031006100610063003800350034009014CD16FC878CEA2DE91C8C681AE86C7C062D8BD88C4CEE501A89FEAC47356D7181644A350F72B5F6023DA2B9E26C5A2522C08B1910D390068CF26794F4BA7B0298A6676B4DC6DED913E3B077B56224D2E1A3FE4EF33F58FE44CFC3DD67E54FB15BE8E29ABAF8357F378FBEDA3EBF9868A54746074D5E0E798047867E1ABD39AD0645BB8E071C72BFC37C007CBFC58F5690A5253F444E77169B2FE92FD95897A412B2078DA3804A00723D6DF824FCA527208A1DFB377B5BA16B620213F8252E10E7D7A3719A3FBB2F7A8189792B0BCF737236963C7DDCA6366F7B04F127925A1F8DDBB1B5A01D280BD300ECA3B1F31F24C8A0D517AE7BCBC3233A24E83B70A334754098DE373A1C027A4D09BB1D26C930E7501EB02464C519D19CFA0B296238AF11638C2E0688C7599E3DB1714AACF4EBFCEF63E1EE521A8E38E3BEFD4EF4991A15E8DD5CFD94E58E68754F3E90BC117025C01562F6440417A42612BE9C8871A18108CBE3E96DA7E35C45171C03E1DFBB3CA1E35A6D322F2D5B79E2BF2A07F14136DA4A768E08E2A7F1A42E04B717CB6AE3D1A3FA0EACCFC9CEC27DB53761E13DE1F55B410A65FB441D50CF8B2153B64925B1CEBDE062B5CAF4C99C41FED6836327037C46515710F16DC611305A0EBA1943A9BA5CC6889626990879713E9C95BB54D6A8A3C1C05A10AFE142B2487A1F0A07B57841E940CC9816E3F43CAE3CB7
)
