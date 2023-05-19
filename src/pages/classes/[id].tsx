import AssessmentCard from '@/components/classes/AssessmentCard';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { Container, Divider, Title } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { GetServerSideProps } from 'next';
import { getSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import Header from '@/components/layout/Header';
import React from 'react';

type Props = {
  assessments: classroom_v1.Schema$CourseWork[];
};

export default function ClassPage({ assessments }: Props) {
  const router = useRouter();

  return (
    <Layout>
      <Title size={23}>{router.query.name}</Title>
      <Divider my="lg" />
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
