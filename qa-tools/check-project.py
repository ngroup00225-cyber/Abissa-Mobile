from pathlib import Path
import importlib.util,unittest,tempfile,json,ast,re,plistlib,sys
ROOT=Path(__file__).resolve().parents[1]
(ROOT/'qa-tools/audit-20261012').mkdir(parents=True,exist_ok=True)
spec=importlib.util.spec_from_file_location('bundle',ROOT/'ios/scripts/validate_web_bundle.py');bundle=importlib.util.module_from_spec(spec);spec.loader.exec_module(bundle)
fixture='<div id="menuGrid"></div><div id="page-accueil"></div><script>window.AbissaNative212={};window.AbissaCommunity={};</script><img src="abissa-resource://app/image.png">'
class Checks(unittest.TestCase):
 def test_real_bundle(self):self.assertGreater(bundle.validate_web_bundle(ROOT/'ios/AbissaMobile/Web')['localReferences'],56)
 def test_missing(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'abissa_mobile.html').write_text(fixture);self.assertRaises(ValueError,bundle.validate_web_bundle,p)
 def test_css_missing(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'image.png').write_bytes(b'x');(p/'abissa_mobile.html').write_text(fixture+'<style>.a{background:url(missing.png)}</style>');self.assertRaises(ValueError,bundle.validate_web_bundle,p)
 def test_css_nested(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'image.png').write_bytes(b'x');(p/'theme').mkdir();(p/'theme/a.css').write_text('.a{background:url(../image.png)}');(p/'abissa_mobile.html').write_text(fixture+'<link rel="stylesheet" href="theme/a.css">');self.assertEqual(bundle.validate_web_bundle(p)['localReferences'],2)
 def test_case(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'Image.png').write_bytes(b'x');(p/'abissa_mobile.html').write_text(fixture);self.assertRaises(ValueError,bundle.validate_web_bundle,p)
 def test_traversal(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'abissa_mobile.html').write_text(fixture.replace('image.png','%2e%2e/secret'));self.assertRaises(ValueError,bundle.validate_web_bundle,p)
 def test_encoded_filename(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'two words.png').write_bytes(b'x');(p/'abissa_mobile.html').write_text(fixture.replace('image.png','two%20words.png'));self.assertEqual(bundle.validate_web_bundle(p)['localReferences'],1)
 def test_js_template_is_not_file(self):
  with tempfile.TemporaryDirectory() as t:
   p=Path(t);(p/'image.png').write_bytes(b'x');(p/'abissa_mobile.html').write_text(fixture+'<script>const s=`<div style="background:url(${e.image})">`;</script>');bundle.validate_web_bundle(p)
 def test_identity(self):
  s=(ROOT/'app/build.gradle').read_text();self.assertIn('applicationId "ci.abissa"',s);self.assertIn('minSdk 24',s);self.assertIn('targetSdk 36',s);self.assertIn('?: "27"',s)
 def test_manual_only(self):
  s=(ROOT/'codemagic.yaml').read_text();self.assertNotIn('publishing:',s);self.assertNotIn('triggering:',s)
 def test_ready_requires_resources(self):self.assertIn('resourceHandler.failedResourceCount == 0',(ROOT/'ios/AbissaMobile/AbissaViewController.swift').read_text())
 def test_python_syntax(self):
  for p in list((ROOT/'ios/scripts').glob('*.py'))+[ROOT/'qa-tools/bundle-mobile.py',ROOT/'qa-tools/bundle-android-interface.py',ROOT/'qa-tools/test-ios-simulator-212.py']:ast.parse(p.read_text(encoding='utf-8'))
result=unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(Checks));report={'tests':result.testsRun,'failures':len(result.failures),'errors':len(result.errors),'actualIosWebKitExecuted':False};(ROOT/'qa-tools/audit-20261012/portable-tests.json').write_text(json.dumps(report,indent=2));sys.exit(not result.wasSuccessful())
