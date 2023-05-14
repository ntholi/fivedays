import AssessmentCard from '@/components/classes/AssessmentCard';
import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import {
  Text,
  Container,
  Divider,
  Accordion,
  Title,
  createStyles,
  rem,
  Group,
  Paper,
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

  return (
    <Layout>
      <Title size={23}>{router.query.name}</Title>
      <Divider my='lg' />
      <Container>
        {assessments.map((it) => (
          <AssessmentCard key={it.id} courseWork={it} />
        ))}
      </Container>
    </Layout>
  );
}

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
