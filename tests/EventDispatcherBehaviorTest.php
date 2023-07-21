<?php


/**
 * @author William Durand <william.durand1@gmail.com>
 */
class EventDispatcherBehaviorTest extends PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $tables = array(
            'Post' => <<<EOF
<database name="event_dispatcher_behavior" defaultIdMethod="native">
    <table name="post">
        <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
        <column name="name" type="VARCHAR" required="true" />

        <behavior name="event_dispatcher" />
    </table>
</database>
EOF
            ,
            'Thread' => <<<EOF
<database name="event_dispatcher_behavior2" defaultIdMethod="native">
    <table name="thread">
        <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
        <column name="text" type="VARCHAR" required="true" />
        <column name="allowed" type="boolean" required="true" defaultValue="false" />

        <behavior name="event_dispatcher" />
    </table>
</database>
EOF
        );

        foreach ($tables as $className => $schema) {
            if (!class_exists($className)) {
                $builder = new PropelQuickBuilder();
                $config  = $builder->getConfig();
                $config->setBuildProperty('behavior.event_dispatcher.class', '../src/EventDispatcherBehavior');
                $builder->setConfig($config);
                $builder->setSchema($schema);

                $builder->build();
            }
        }
    }

    public function testObjectMethods()
    {
        $this->assertTrue(method_exists('Post', 'getEventDispatcher'));
        $this->assertTrue(method_exists('Post', 'setEventDispatcher'));
        $this->assertTrue(defined('Post::EVENT_PRE_SAVE'));
        $this->assertTrue(defined('Post::EVENT_POST_SAVE'));
        $this->assertTrue(defined('Post::EVENT_PRE_UPDATE'));
        $this->assertTrue(defined('Post::EVENT_POST_UPDATE'));
        $this->assertTrue(defined('Post::EVENT_PRE_INSERT'));
        $this->assertTrue(defined('Post::EVENT_POST_INSERT'));
        $this->assertTrue(defined('Post::EVENT_PRE_DELETE'));
        $this->assertTrue(defined('Post::EVENT_POST_DELETE'));
        $this->assertTrue(defined('Post::EVENT_CONSTRUCT'));
        $this->assertTrue(defined('Post::EVENT_POST_HYDRATE'));
    }

    public function testGetDispatcher()
    {
        $post = new Post();
        $dispatcher = $post->getEventDispatcher();

        $this->assertNotNull($dispatcher);
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcher', $dispatcher);
    }

    public function testFireEvent()
    {
        $preSaveFired  = false;
        $postSaveFired = false;
        $postConstructFired = false;
        $postHydrateFired  = false;
        $threadConstructFired = false;

        $that = $this;

        Post::getEventDispatcher()->addListener(Post::EVENT_POST_HYDRATE, function (object $event) use (& $postHydrateFired, $that) {
            $postHydrateFired = true;

            $that->assertInstanceOf('Symfony\Component\EventDispatcher\GenericEvent', $event);
            $that->assertInstanceOf('Post', $event->getSubject());
            $that->assertFalse($event->hasArgument('connection'));
        });

        Post::getEventDispatcher()->addListener(Post::EVENT_CONSTRUCT, function (object $event) use (& $postConstructFired, $that) {
            $postConstructFired = true;

            $that->assertInstanceOf('Symfony\Component\EventDispatcher\GenericEvent', $event);
            $that->assertInstanceOf('Post', $event->getSubject());
            $that->assertFalse($event->hasArgument('connection'));
        });

        Thread::getEventDispatcher()->addListener(Thread::EVENT_CONSTRUCT, function (object $event) use (& $threadConstructFired, $that) {
            $threadConstructFired = true;

            $that->assertInstanceOf('Symfony\Component\EventDispatcher\GenericEvent', $event);
            $that->assertInstanceOf('Thread', $event->getSubject());
            $that->assertFalse($event->hasArgument('connection'));
        });

        Post::getEventDispatcher()->addListener(Post::EVENT_PRE_SAVE, function (object $event) use (& $preSaveFired, $that) {
            $preSaveFired = true;

            $that->assertInstanceOf('Symfony\Component\EventDispatcher\GenericEvent', $event);
            $that->assertInstanceOf('Post', $event->getSubject());
            $that->assertInstanceOf('PropelPDO', $event->getArgument('connection'));
        });

        Post::getEventDispatcher()->addListener(Post::EVENT_POST_SAVE, function (object $event) use (& $postSaveFired, $that) {
            $postSaveFired = true;

            $that->assertInstanceOf('Symfony\Component\EventDispatcher\GenericEvent', $event);
            $that->assertInstanceOf('Post', $event->getSubject());
            $that->assertInstanceOf('PropelPDO', $event->getArgument('connection'));
        });

        new Thread();
        $this->assertTrue($threadConstructFired);


        $post = new Post();
        $this->assertTrue($postConstructFired);

        $post->setName('a-name');
        $post->save();
        $post->reload();

        $this->assertTrue($postHydrateFired);
        $this->assertTrue($preSaveFired);
        $this->assertTrue($postSaveFired);
    }

    public function testObjectImplementsAnInterface()
    {
        $reflClass = new ReflectionClass('Post');
        $this->assertTrue($reflClass->implementsInterface('EventDispatcherAwareModelInterface'));
    }
}
