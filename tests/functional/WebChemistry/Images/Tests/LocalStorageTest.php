<?php
namespace WebChemistry\Images\Tests;

use Nette\Http\FileUpload;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Utils\Image;
use WebChemistry\Images\Image\ImageFactory;
use WebChemistry\Images\ImageStorageException;
use WebChemistry\Images\Modifiers\Composite;
use WebChemistry\Images\Modifiers\ModifierContainer;
use WebChemistry\Images\Parsers\ModifierParser;
use WebChemistry\Images\Resources\IFileResource;
use WebChemistry\Images\Resources\ImageResource;
use WebChemistry\Images\Resources\ITransferResource;
use WebChemistry\Images\Resources\ResourceException;
use WebChemistry\Images\Storages\LocalStorage;
use WebChemistry\Testing\TUnitTest;

class LocalStorageTest extends \Codeception\Test\Unit {

	use TUnitTest;

	/** @var LocalStorage */
	private $storage;

	protected function _before() {
		@mkdir(__DIR__ . '/output');
		$modifierContainer = new ModifierContainer();
		$url = new UrlScript('http://example.com/');
		$request = new Request($url);
		$imageFactory = new ImageFactory();
		$this->fillAliases($modifierContainer);
		$this->storage = new LocalStorage(__DIR__, 'output', $modifierContainer, $request, $imageFactory, 'default/upload.gif');
	}

	private function fillAliases(ModifierContainer $modifierContainer) {
		$modifierContainer->addAlias('resize', ModifierParser::parse('resize:5,5,exact'));
		$modifierContainer->addAlias('resize2', ModifierParser::parse('resize:5,5'));
		$modifierContainer->addAlias('resizeVar', ModifierParser::parse('resize:$1,$2,$3'));
	}

	private function createUploadResource() {
		if (!file_exists(UPLOAD_GIF)) {
			copy(IMAGE_GIF, UPLOAD_GIF);
		}
		$upload = new FileUpload([
			'name' => 'upload.gif',
			'tmp_name' => UPLOAD_GIF,
			'type' => 'image/gif',
			'error' => 0,
			'size' => 1,
		]);

		return $this->storage->createUploadResource($upload);
	}

	private function sameOriginal($path) {
		return md5_file(IMAGE_GIF) === md5_file($path);
	}

	private function getUploadPath($name = 'upload.gif', $namespace = 'original') {
		return __DIR__ . '/output/' . $namespace . '/' . $name;
	}

	private function createImageResource($id = 'upload.gif') {
		$resource = $this->storage->createLocalResource(IMAGE_GIF);
		$resource->setId($id);

		return $resource;
	}

	protected function _after() {
		$this->services->fileSystem->removeDirRecursive(__DIR__ . '/output');
	}

	public function testSaveImage() {
		$resource = $this->createImageResource();

		$output = $this->storage->save($resource);
		$this->assertFileExists($path = $this->getUploadPath());
		$this->sameOriginal($path);
		$this->assertInstanceOf(IFileResource::class, $output);
	}

	public function testSaveUpload() {
		$output = $this->storage->save($this->createUploadResource());

		$this->assertFileExists($path = $this->getUploadPath());
		$this->sameOriginal($path);
		$this->assertInstanceOf(IFileResource::class, $output);
	}

	public function testSaveTwice() {
		$this->assertThrownException(function () {
			$resource = $this->createUploadResource();
			$this->storage->save($resource);
			$this->storage->save($resource);
		}, ResourceException::class);
	}

	public function testSaveNamespace() {
		$upload = $this->createUploadResource();
		$upload->setNamespace('namespace');
		$this->storage->save($upload);

		$this->assertFileExists($this->getUploadPath('upload.gif','namespace/original'));
	}

	public function testUniqueImage() {
		$dir = __DIR__ . '/output/original';
		$this->storage->save($this->createUploadResource());

		$this->assertSame(1, $this->services->fileSystem->fileCount($dir));

		$this->storage->save($this->createUploadResource());
		$this->assertSame(2, $this->services->fileSystem->fileCount($dir));
	}

	public function testModifiers() {
		$resource = $this->createUploadResource();
		$resource->setAlias('resize');
		$this->storage->save($resource);

		$size = getimagesize($this->getUploadPath());
		$this->assertSame(5, $size[0]);
		$this->assertSame(5, $size[1]);
	}

	public function testResize() {
		$result = $this->storage->save($this->createUploadResource());
		$result->setAlias('resize2');
		$this->storage->save($result);

		$this->assertFileExists(__DIR__ . '/output/resize2/upload.gif');
		$size = getimagesize($this->getUploadPath('upload.gif', 'resize2'));
		$this->assertSame(5, $size[0]);
		$this->assertSame(5, $size[1]);
	}

	public function testDelete() {
		$result = $this->storage->save($this->createUploadResource());
		$result->setAlias('resize2');
		$result = $this->storage->save($result);

		$this->assertFileExists(__DIR__ . '/output/resize2/upload.gif');
		$this->assertFileExists(__DIR__ . '/output/original/upload.gif');
		$this->storage->delete($result);
		$this->assertFileNotExists(__DIR__ . '/output/resize2/upload.gif');
		$this->assertFileNotExists(__DIR__ . '/output/original/upload.gif');
	}

	public function testCopy() {
		$result = $this->storage->save($this->createUploadResource());

		$need = $this->storage->createResource('copy.gif');
		$need->setAlias('resize');

		$this->storage->copy($result, $need);
		$this->assertFileExists($this->getUploadPath('copy.gif'));

		$size = getimagesize($this->getUploadPath('copy.gif'));
		$this->assertSame(5, $size[0]);
		$this->assertSame(5, $size[1]);
	}

	public function testCopySameDest() {
		$this->assertThrownException(function () {
			$src = $this->storage->createResource('namespace/upload.gif');
			$dest = $this->storage->createResource('namespace/upload.gif');

			$this->storage->copy($src, $dest);
		}, ImageStorageException::class);
	}

	public function testMove() {
		$result = $this->storage->save($this->createUploadResource());

		$need = $this->storage->createResource('copy.gif');
		$need->setAlias('resize');

		$this->storage->move($result, $need);
		$this->assertFileExists($this->getUploadPath('copy.gif'));
		$this->assertFileNotExists($this->getUploadPath());
	}

	public function testLink() {
		$result = $this->storage->save($this->createUploadResource());
		$this->assertSame('/output/original/upload.gif', $this->storage->link($result));
	}

	public function testLinkNoImage() {
		$resource = $this->storage->createResource('notExists.gif');

		$this->assertSame(NULL, $this->storage->link($resource));
	}

	public function testLinkDefaultImage() {
		$upload = $this->createUploadResource();
		$upload->setNamespace('default');
		$this->storage->save($upload);

		$resource = $this->storage->createResource('notExists.gif');

		$this->assertSame('/output/default/original/upload.gif', $this->storage->link($resource));
	}

	public function testImageSize() {
		$result = $this->storage->save($this->createUploadResource());
		$size = $this->storage->getImageSize($result);

		$this->assertSame(14, $size->getWidth());
		$this->assertSame(14, $size->getHeight());
	}

	public function testResizeWithVariables() {
		$result = $this->storage->save($this->createUploadResource());
		$result->setAlias('resizeVar', [20, 20, 'exact']);
		$this->storage->save($result);

		$this->assertFileExists(__DIR__ . '/output/resizeVar_20_20_exact/upload.gif');
		$size = getimagesize($this->getUploadPath('upload.gif', 'resizeVar_20_20_exact'));
		$this->assertSame(20, $size[0]);
		$this->assertSame(20, $size[1]);
	}

}
