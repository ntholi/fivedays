import ClassCard from '@/components/classes/ClassCard';
import Layout from '@/components/layout/Layout';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { SimpleGrid } from '@mantine/core';
import { classroom_v1, google } from 'googleapis';
import { GetServerSideProps } from 'next';
import { getSession, useSession } from 'next-auth/react';
import { FC } from 'react';

interface ClassesPageProps {
  classes: classroom_v1.Schema$Course[];
}

const ClassesPage: FC<ClassesPageProps> = ({ classes }) => {
  return (
    <Layout>
      <SimpleGrid
        cols={4}
        breakpoints={[
          { maxWidth: 'sm', cols: 2 },
          { maxWidth: 'xs', cols: 1 },
        ]}
      >
        {classes.map((item) => (
          <ClassCard key={item.id} item={item} />
        ))}
      </SimpleGrid>
    </Layout>
  );
};

export const getServerSideProps: GetServerSideProps = async (context) => {
  const session = await getSession(context);
  const classroom = googleClassroom(session);

  try {
    const response = await classroom.courses.list();
    const classes = response.data.courses || [];

    return {
      props: {
        classes,
      },
    };
  } catch (err) {
    console.error('Error: ', err);
    return {
      props: {
        classes: [],
      },
    };
  }
};

export default ClassesPage;
