import {
  createStyles,
  Image,
  Container,
  Title,
  Button,
  Group,
  Text,
  List,
  ThemeIcon,
  rem,
  useMantineColorScheme,
  Divider,
} from '@mantine/core';
import {
  IconBrandGithub,
  IconBrandGoogle,
  IconBrandGoogleHome,
  IconBrandOpenai,
  IconCheck,
  IconSchool,
} from '@tabler/icons-react';
import Link from 'next/link';
import Logo from '../layout/Logo';

const useStyles = createStyles((theme) => ({
  inner: {
    display: 'flex',
    justifyContent: 'space-between',
    paddingBottom: `calc(${theme.spacing.xl} * 4)`,
  },

  content: {
    maxWidth: rem(480),
    marginRight: `calc(${theme.spacing.xl} * 3)`,

    [theme.fn.smallerThan('md')]: {
      maxWidth: '100%',
      marginRight: 0,
    },
  },

  title: {
    color: theme.colorScheme === 'dark' ? theme.white : theme.black,
    fontFamily: `Greycliff CF, ${theme.fontFamily}`,
    fontSize: rem(44),
    lineHeight: 1.2,
    fontWeight: 'bold',

    [theme.fn.smallerThan('xs')]: {
      fontSize: rem(28),
    },
  },

  control: {
    [theme.fn.smallerThan('xs')]: {
      flex: 1,
    },
  },

  image: {
    flex: 1,

    [theme.fn.smallerThan('md')]: {
      display: 'none',
    },
  },

  highlight: {
    position: 'relative',
    backgroundColor: theme.fn.variant({
      variant: 'light',
      color: theme.primaryColor,
    }).background,
    borderRadius: theme.radius.sm,
    padding: `${rem(4)} ${rem(12)}`,
  },
}));

export default function Hero() {
  const { classes } = useStyles();

  const { colorScheme } = useMantineColorScheme();

  return (
    <div>
      <Container size='lg' mt={120}>
        {/* <Logo size={40} /> */}
        <div className={classes.inner}>
          <div className={classes.content}>
            <Title className={classes.title}>
              An <span className={classes.highlight}>A.I.</span> Powered LMS
            </Title>
            <Text color='dimmed' mt='md'>
              <Text component='span' fw={700} color='blue'>
                Five
              </Text>
              <Text
                component='span'
                fw={700}
                color={colorScheme === 'dark' ? 'white' : 'dark'}
              >
                Days
              </Text>{' '}
              Uses artificial inelegance to assists lecturers with creating and
              grading assessments, integrated with Google Classroom and
              utilizing OpenAI API.
            </Text>

            <List
              mt={30}
              spacing='sm'
              size='sm'
              icon={
                <ThemeIcon size={20} radius='xl'>
                  <IconCheck size={rem(12)} stroke={1.5} />
                </ThemeIcon>
              }
            >
              <List.Item
                icon={
                  <ThemeIcon size={24} radius='xl'>
                    <IconBrandGoogle size='1rem' />
                  </ThemeIcon>
                }
              >
                <b>Google Classroom</b> Fully integrated with Google Classroom
              </List.Item>
              <List.Item
                icon={
                  <ThemeIcon size={24} radius='xl'>
                    <IconBrandOpenai size='1rem' />
                  </ThemeIcon>
                }
              >
                <b>Open AI</b> Uses OpenAI API to assist with assessment
                creation and grading
              </List.Item>
              <List.Item
                icon={
                  <ThemeIcon size={24} radius='xl'>
                    <IconBrandGithub size='1rem' />
                  </ThemeIcon>
                }
              >
                <b>Free and open source</b> all packages have MIT license, you
                can use it for free
              </List.Item>
            </List>

            <Group mt={30}>
              <Link href='/classes'>
                <Button
                  leftIcon={<IconSchool />}
                  radius='xl'
                  size='md'
                  className={classes.control}
                >
                  Get Started
                </Button>
              </Link>
              <Link href='https://github.com/ntholi/fivedays' target='_blank'>
                <Button
                  color='dark'
                  radius='xl'
                  size='md'
                  className={classes.control}
                  leftIcon={<IconBrandGithub />}
                >
                  Source code
                </Button>
              </Link>
            </Group>
          </div>
          <Divider orientation='vertical' mx='xl' />
          <Image
            src='/undraw_teaching.svg'
            alt='Teacher'
            className={classes.image}
          />
        </div>
      </Container>
    </div>
  );
}
