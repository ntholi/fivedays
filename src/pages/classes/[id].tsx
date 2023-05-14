import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import {
  Text,
  Container,
  Divider,
  Paper,
  Title,
  createStyles,
  rem,
  Group,
} from '@mantine/core';
import { IconBook, IconBook2 } from '@tabler/icons-react';
import { classroom_v1 } from 'googleapis';
import { GetServerSideProps } from 'next';
import { getSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React from 'react';

type Props = {
  assessments: classroom_v1.Schema$CourseWork[];
};

export default function ClassPage({ assessments }: Props) {
  const router = useRouter();
  const { classes } = useStyles();

  return (
    <Layout>
      <Title size={23}>{router.query.name}</Title>
      <Divider my='lg' />
      <Container>
        {assessments.map((it) => (
          <Paper className={classes.paper} p='lg' key={it.id}>
            <Group>
              <IconBook2 />
              <Text fw={500} color=''>
                {it.title}
              </Text>
            </Group>
          </Paper>
        ))}
      </Container>
    </Layout>
  );
}

const useStyles = createStyles((theme) => ({
  paper: {
    transition: 'box-shadow 0.3s ease, border-color 0.3s ease',
    '&:hover': {
      borderColor: theme.colors.blue[5],
      boxShadow: `${rem(0)} ${rem(2)} ${rem(4)} ${
        theme.colorScheme === 'dark'
          ? theme.colors.dark[4]
          : theme.colors.gray[6]
      }`,
      cursor: 'pointer',
    },
  },
}));

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  try {
    const res = await classroom.courses.courseWork.list({
      courseId: context.params?.id as string,
    });

    return {
      props: {
        assessments: res.data.courseWork || [],
      },
    };
  } catch (err) {
    console.error('Error: ', err);
    return {
      props: {
        assessments: [],
      },
    };
  }
};
