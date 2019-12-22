<?php
declare(strict_types=1);

/*
 * Adopted from the Symfony Demo
 * @see https://github.com/symfony/demo/blob/master/src/Command/AddUserCommand.php
 */
namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A console command that creates users and stores them in the database.
 *
 * To use this command, open a terminal window, enter into your project
 * directory and execute the following:
 *
 *     $ php bin/console app:add-user
 *
 * To output detailed information, increase the command verbosity:
 *
 *     $ php bin/console app:add-user -vv
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class AddUserCommand extends Command
{
    // to make your command lazily loaded, configure the $defaultName static property,
    // so it will be instantiated only when the command is actually called.
    protected static $defaultName = 'app:add-user';

    /**
     * @var SymfonyStyle
     */
    private $io;

    private $entityManager;
    private $passwordEncoder;
    private $validator;
    private $users;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordEncoderInterface $encoder,
        ValidatorInterface $validator,
        UserRepository $users
    ) {
        parent::__construct();
        $this->entityManager = $em;
        $this->passwordEncoder = $encoder;
        $this->validator = $validator;
        $this->users = $users;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Creates users and stores them in the database')
            ->setHelp($this->getCommandHelp())
            ->addArgument('username', InputArgument::OPTIONAL, 'The unique username of the new user')
            ->addArgument('email', InputArgument::OPTIONAL, 'The email of the new user')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password of the new user')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'If set, the user is created as an administrator')
            ->addOption('process-owner', null, InputOption::VALUE_NONE, 'If set, the user is created as a process owner')
        ;
    }

    /**
     * The command help is usually included in the configure() method, but when
     * it's too long, it's better to define a separate method to maintain the
     * code readability.
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates new users and saves them in the database:
  <info>php %command.full_name%</info> <comment>username email password</comment>

By default the command creates regular users. To create administrator users,
add the <comment>--admin</comment> option:
  <info>php %command.full_name%</info> username email password <comment>--admin</comment>

To create a process-owner,
add the <comment>--process-owner</comment> option:
  <info>php %command.full_name%</info> username email password <comment>--process-owner</comment>
  
If you omit any of the three required arguments, the command will ask you to
provide the missing values.
HELP;
    }

    /**
     * This optional method is the first one executed for a command after configure()
     * and is useful to initialize properties based on the input arguments and options.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * User Wizard
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (null !== $input->getArgument('email')
            && null !== $input->getArgument('password')
        ) {
            return;
        }

        $this->io->title('Add User Command Interactive Wizard');
        $this->io->text([
            'If you prefer to not use this interactive wizard, provide the',
            'arguments required by this command as follows:',
            '',
            ' $ php bin/console app:add-user username email@example.com password',
            '',
            'Now we\'ll ask you for the value of all the missing command arguments.',
        ]);

        // Ask for the username if it's not defined
        $username = $input->getArgument('username');
        if (null !== $username) {
            $this->io->text(' > <info>Username</info>: '.$username);
        } else {
            $email = $this->io->ask('Username', null);
            $input->setArgument('username', $email);
        }

        // Ask for the email if it's not defined
        $email = $input->getArgument('email');
        if (null !== $email) {
            $this->io->text(' > <info>Email</info>: '.$email);
        } else {
            $email = $this->io->ask('Email', null);
            $input->setArgument('email', $email);
        }

        // Ask for the password if it's not defined
        $password = $input->getArgument('password');
        if (null !== $password) {
            $this->io->text(' > <info>Password</info>: '.str_repeat('*', mb_strlen($password)));
        } else {
            $password = $this->io->askHidden('Password (your type will be hidden, leave empty to auto-generate a password)');
            $input->setArgument('password', $password);
        }
    }

    /**
     * This method is executed after interact() and initialize(). It usually
     * contains the logic to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('add-user-command');
        $plainPassword = $input->getArgument('password');
        $email = $input->getArgument('email');
        $username = $input->getArgument('username');
        $isAdmin = $input->getOption('admin');
        $isPO = $input->getOption('process-owner');

        // create the user and encode its password
        $user = new User();
        $user->setEmail((string)$email);
        $user->setUsername((string)$username);

        $roles = [];
        if ($isAdmin) {
            $roles[] = User::ROLE_ADMIN;
        } else if ($isPO) {
            $roles[] = User::ROLE_PROCESS_OWNER;
        }
        $user->setRoles($roles);

        if (empty(trim((string)$plainPassword))) {
            $plainPassword = bin2hex(random_bytes(6));
            $this->io->comment(sprintf('Using generated password: %s', $plainPassword));
        }

        $encodedPassword = $this->passwordEncoder->encodePassword($user, $plainPassword);
        $user->setPassword($encodedPassword);

        // make sure the user data is correct
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            /*
             * Uses a __toString method on the $errors variable which is a
             * ConstraintViolationList object. This gives us a nice string
             * for debugging.
             */
            $errorsString = (string) $errors;

            $this->io->error(sprintf("User was not created, invalid data supplied:\n%s", $errorsString));
            return;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->io->success(sprintf('%s was successfully created: %s (%s)', $isAdmin ? 'Administrator user' : 'User', $user->getUsername(), $user->getEmail()));
        $event = $stopwatch->stop('add-user-command');

        if ($output->isVerbose()) {
            $this->io->comment(sprintf('New user database id: %d / Elapsed time: %.2f ms / Consumed memory: %.2f MB', $user->getId(), $event->getDuration(), $event->getMemory() / (1024 ** 2)));
        }
    }
}
